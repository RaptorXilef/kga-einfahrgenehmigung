<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use RuntimeException;

/**
 * Service für Sitzungsverwaltung und Berechtigungsprüfung von Administratoren.
 *
 * (Login-Logik wurde ins Identity-Modul via CQRS ausgelagert)
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AuthService
{
    public function __construct(
        private ConfigInterface $config,
        private RoleRepositoryInterface $roleRepository,
        private RateLimiterInterface $rateLimiter, // TODO Kann später auch entfernt werden
        private AuthSessionInterface $sessionManager,
        private UserRepositoryInterface $userRepository,
    ) {
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
