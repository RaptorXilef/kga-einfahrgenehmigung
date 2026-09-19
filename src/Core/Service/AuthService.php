<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use RuntimeException;

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
        private RoleRepositoryInterface $roleRepository,
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
            throw new RuntimeException('Zu viele fehlgeschlagene Login-Versuche. Ihre IP-Adresse wurde gesperrt.');
        }

        if ($this->attemptSystemLogin($username, $password, $ip)) {
            return true;
        }

        $users = $this->userRepository->loadAll();
        foreach ($users as $userId => $user) {
            if ($user->username !== $username) {
                continue;
            }

            if (\password_verify($password, $user->passwordHash)) {
                $this->setupSession((string) $userId, $user->roleId, $username, $user->passwordHash);
                $this->rateLimiter->clearAttempts($ip);

                return true;
            }
        }

        // Timing-Attack-Prevention Dummy Hash
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
        $this->sessionManager->rotateCsrfToken();
    }

    public function isLoggedIn(): bool
    {
        try {
            $this->validateActiveSession();
        } catch (RuntimeException) {
            return false;
        }

        return $this->sessionManager->getUserId() !== ''
            || $this->sessionManager->getAdminUser() === ($this->config->get('superadmin')['label'] ?? 'Dev-Admin')
            || $this->sessionManager->getAdminUser() === ($this->config->get('backdoor')['label'] ?? '');
    }

    public function hasPermission(string $permission): bool
    {
        $uid = $this->sessionManager->getUserId();
        if (\str_starts_with($uid, 'sys_')) { // TODO Schwachstelle beheben!
            return true;
        }

        $roleId = $this->sessionManager->getAdminGroup();
        $roles = $this->roleRepository->loadAll();

        if (isset($roles[$roleId]) && \in_array('*', $roles[$roleId]->permissions, true)) {
            return true;
        }

        return ($this->sessionManager->getPermissions()[$permission] ?? false) === true;
    }

    public function refreshSessionPermissions(string $roleId): void
    {
        $roles = $this->roleRepository->loadAll();
        $rolePerms = isset($roles[$roleId]) ? $roles[$roleId]->permissions : [];
        $structure = $this->config->get('structure', []);

        $compiler = new PermissionCompiler();
        $this->sessionManager->setPermissions($compiler->compile(\is_array($structure) ? $structure : [], $rolePerms));
    }

    public function getUsername(): string
    {
        return $this->sessionManager->getAdminUser();
    }

    public function getUserId(): string
    {
        return $this->sessionManager->getUserId();
    }

    public function getRole(): string
    {
        return $this->sessionManager->getAdminGroup();
    }

    public function generateId(string $prefix = ''): string
    {
        return $prefix . \bin2hex(\random_bytes(8));
    }

    private function attemptSystemLogin(string $identifier, string $password, string $ip): bool
    {
        if ($this->attemptBackdoorLogin($identifier, $password, $ip)) {
            return true;
        }

        return $this->attemptSuperadminLogin($identifier, $password, $ip);
    }

    private function attemptBackdoorLogin(string $identifier, string $password, string $ip): bool
    {
        if ($this->config->get('disable_backdoor', false) === true) {
            return false;
        }

        $backdoor = $this->config->get('backdoor');
        if (!\is_array($backdoor)) {
            return false;
        }

        $bdUser = \is_string($backdoor['user'] ?? null) ? $backdoor['user'] : '';
        $bdPass = \is_string($backdoor['pass'] ?? null) ? $backdoor['pass'] : '';

        if ($identifier === $bdUser && $bdUser !== '' && \password_verify($password, $bdPass)) {
            $this->setupSession('sys_backdoor', 'admin', \is_string($backdoor['label'] ?? null) ? $backdoor['label'] : 'System-Inhaber');
            $this->rateLimiter->clearAttempts($ip);

            return true;
        }

        return false;
    }

    private function attemptSuperadminLogin(string $identifier, string $password, string $ip): bool
    {
        if ($this->config->get('disable_superadmin', false) === true) {
            return false;
        }

        $superAdmins = $this->config->get('superadmins');
        if (!\is_array($superAdmins)) {
            return false;
        }

        foreach ($superAdmins as $saUser => $adminCfg) {
            if (!\is_string($saUser) || !\is_array($adminCfg) || $identifier !== $saUser || $saUser === '') {
                continue;
            }

            $saPass = \is_string($adminCfg['pass'] ?? null) ? $adminCfg['pass'] : '';
            $saLabel = \is_string($adminCfg['label'] ?? null) ? $adminCfg['label'] : 'Systembetreuer';

            // FIX: Verhindere "Pass the Hash". Prüfe strikt, ob es sich um einen Hash handelt!
            $isHash = \str_starts_with($saPass, '$2y$') || \str_starts_with($saPass, '$argon');

            $isValid = $isHash ? \password_verify($password, $saPass) : $password === $saPass;

            if ($isValid) {
                $this->setupSession('sys_superadmin', 'admin', $saLabel);
                $this->rateLimiter->clearAttempts($ip);

                return true;
            }
        }

        return false;
    }

    private function setupSession(string $userId, string $roleId, string $label, ?string $hash = null): void
    {
        $this->sessionManager->regenerate();
        $this->sessionManager->rotateCsrfToken();
        $this->sessionManager->setAuthSession($userId, $roleId, $label, $hash);
        $this->refreshSessionPermissions($roleId);
    }

    private function validateActiveSession(): void
    {
        $userId = $this->sessionManager->getUserId();
        if ($userId === '' || \str_starts_with($userId, 'sys_')) {
            return;
        }

        $users = $this->userRepository->loadAll();
        if (!isset($users[$userId])) {
            $this->logout();

            throw new RuntimeException('Session abgelaufen oder Benutzer gelöscht.');
        }

        $sessionHash = $this->sessionManager->getAuthHash();
        if ($sessionHash === null || !\hash_equals($sessionHash, $users[$userId]->passwordHash)) {
            $this->logout();

            throw new RuntimeException('Sicherheits-Token ungültig (Passwort wurde eventuell geändert).');
        }

        $this->refreshSessionPermissions($users[$userId]->roleId);
    }
}
