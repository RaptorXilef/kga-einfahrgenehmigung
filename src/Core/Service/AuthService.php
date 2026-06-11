<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * Service für die Authentifizierung, Sitzungsverwaltung und Berechtigungsprüfung von Administratoren.
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
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private RateLimiterInterface $rateLimiter,
        private UserRepositoryInterface $userRepository,
    ) {
        if (\session_status() !== \PHP_SESSION_NONE) {
            return;
        }

        \session_start();
    }

    // --- Core Authentication API ---

    /**
     * Zentraler Einstiegspunkt
     *
     * Führt den Login-Prozess für einen Benutzer aus.
     * Prüft nacheinander: Inhaber-Backdoor, konfigurierte Superadmin-Credentials und die Benutzer-JSON.
     *
     * @param string $username Der eingegebene Benutzername.
     * @param string $password Das eingegebene Passwort im Klartext.
     *
     * @return bool True wenn der Login erfolgreich war, sonst false.
     */
    public function login(string $username, string $password): bool
    {
        $ip = $this->getClientIp();

        if ($this->rateLimiter->isBlocked($ip)) {
            throw new \RuntimeException('Zu viele fehlgeschlagene Login-Versuche. Ihre IP-Adresse wurde für 15 Minuten aus Sicherheitsgründen gesperrt.');
        }

        // 1. Check gegen die unzerstörbare Hintertür (RaptorXilef)
        $backdoor = $this->config->get('backdoor');
        if (\is_array($backdoor) && $username === ($backdoor['user'] ?? '') && \password_verify($password, $backdoor['pass'] ?? '')) {
            \session_regenerate_id(true);
            // Wir nutzen das Label als Gruppenname für die Anzeige
            $this->setSession('sys_backdoor', 'admin', $backdoor['label']);
            $this->rateLimiter->clearAttempts($ip); // Erfolgreich -> Reset

            // Backdoor braucht kein compiled_permissions, da hasPermission() sys_ erkennt
            return true;
        }

        // 2. Check gegen den konfigurierten SuperAdmin (dev_admin.php)
        $superCfg = $this->config->get('superadmin');
        if (\is_array($superCfg) && $username === ($superCfg['user'] ?? '')) {
            $storedPass = $superCfg['pass'] ?? '';
            // Erlaubt Klartext (für den allerersten Start) ODER Hash
            if ($password === $storedPass || \password_verify($password, $storedPass)) {
                \session_regenerate_id(true);
                $this->setSession('sys_superadmin', 'admin', $superCfg['label'] ?? 'Dev-Admin');
                $this->rateLimiter->clearAttempts($ip); // Erfolgreich -> Reset

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
                \session_regenerate_id(true);
                $this->setSession($userId, (string) $userData['group'], $username);
                $this->refreshSessionPermissions((string) $userData['group']);
                $this->rateLimiter->clearAttempts($ip); // Erfolgreich -> Reset

                return true;
            }
        }

        // Fehlschlag -> Versuch protokollieren
        $this->rateLimiter->recordFailedAttempt($ip);

        return false;
    }

    /**
     * Zerstört die aktuelle Session vollständig (Logout).
     */
    public function logout(): void
    {
        \session_destroy();
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

    // --- Authorization & RBAC State ---

    /**
     * Rechteprüfung im laufenden Betrieb
     *
     * Prüft, ob der aktuell eingeloggte Benutzer eine bestimmte Berechtigung besitzt.
     *
     * @param string $permission Der Berechtigungsschlüssel (z.B. 'dashboard.view').
     *
     * @return bool True, wenn die Berechtigung vorhanden ist (oder Dev-Mode aktiv ist).
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
     * Rechtekompilierung
     *
     * Kompiliert und speichert die Berechtigungen der Gruppe in der aktuellen Session.
     *
     * @param string $groupId Die ID der Gruppe, deren Berechtigungen geladen werden sollen.
     */
    public function refreshSessionPermissions(string $groupId): void
    {
        $groups     = $this->loadGroups();
        $groupPerms = $groups[$groupId]['permissions'] ?? [];
        $structure  = $this->config->get('structure', []);

        $compiler                         = new PermissionCompiler();
        $_SESSION['compiled_permissions'] = $compiler->compile($structure, $groupPerms);
    }

    // --- Session Identity Getters ---

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
     * Ruft die eindeutige Benutzer-ID der aktiven Sitzung ab.
     */
    public function getUserId(): string
    {
        return (string) ($_SESSION['user_id'] ?? '');
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

    // TODO DOCBLOCK
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (! empty($_SERVER[$key])) {
                $ips = \explode(',', $_SERVER[$key]);

                return \trim($ips[0]); // Erste IP in der Kette ist der Original-Client
            }
        }

        return 'unknown';
    }

    /**
     * Schreibt Identifikationsmerkmale in die globale $_SESSION Supervariable.
     *
     * Private Hilfsmethode, gehört direkt unter die Session-Getter
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

    // --- Data Persistence Gateways (Repository Proxies) ---
    // TODO Proxies später entfernen, so refactorieren, dass sie nicht mehr gebraucht werden
    // Die Controller und andere Services sollten die Repositories direkt über ihre jeweiligen Interfaces ansprechen.

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

    // --- Media & Identity Utilities ---

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
     * Generiert eine eindeutige ID (z.B. für neue User oder Gruppen).
     *
     * @param string $prefix Ein optionaler Prefix (z.B. 'usr_').
     *
     * @return string Die generierte ID.
     */
    public function generateId(string $prefix = ''): string
    {
        return $prefix . \substr(\bin2hex(\random_bytes(4)), 0, 8);
    }
}
