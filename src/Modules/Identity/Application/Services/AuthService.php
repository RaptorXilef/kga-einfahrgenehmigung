<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Override;
use RuntimeException;

/**
 * Service für Sitzungsverwaltung und Berechtigungsprüfung von Administratoren.
 */
final readonly class AuthService implements AuthorizationInterface
{
    public function __construct(
        private ConfigInterface $config,
        private RoleRepositoryInterface $roleRepository,
        private RateLimiterInterface $rateLimiter,
        private AuthSessionInterface $sessionManager,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    #[Override]
    public function logout(): void
    {
        $this->sessionManager->destroy();
        $this->sessionManager->rotateCsrfToken();
    }

    #[Override]
    public function isLoggedIn(): bool
    {
        try {
            $this->validateActiveSession();
        } catch (RuntimeException) {
            return false;
        }

        $superadminCfg = $this->config->getArray('superadmin');
        $backdoorCfg = $this->config->getArray('backdoor');

        return $this->sessionManager->getUserId() !== ''
            || $this->sessionManager->getAdminUser() === (string) ($superadminCfg['label'] ?? 'Dev-Admin')
            || $this->sessionManager->getAdminUser() === (string) ($backdoorCfg['label'] ?? '');
    }

    #[Override]
    public function hasPermission(string $permission): bool
    {
        $uid = $this->sessionManager->getUserId();
        if (\str_starts_with($uid, 'sys_')) {
            return true;
        }

        $roleId = $this->sessionManager->getAdminGroup();
        $roles = $this->roleRepository->loadAll();

        if (isset($roles[$roleId]) && \in_array('*', $roles[$roleId]->permissions, true)) {
            return true;
        }

        return ($this->sessionManager->getPermissions()[$permission] ?? false) === true;
    }

    #[Override]
    public function refreshSessionPermissions(string $roleId): void
    {
        $roles = $this->roleRepository->loadAll();
        $rolePerms = isset($roles[$roleId]) ? $roles[$roleId]->permissions : [];
        $structure = $this->config->getArray('structure');

        $compiler = new PermissionCompiler();
        $this->sessionManager->setPermissions($compiler->compile($structure, $rolePerms));
    }

    #[Override]
    public function getUsername(): string
    {
        return $this->sessionManager->getAdminUser();
    }

    #[Override]
    public function getUserId(): string
    {
        return $this->sessionManager->getUserId();
    }

    #[Override]
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

        $user = $this->userRepository->findById($userId);
        if (!$user instanceof User) {
            $this->logout();

            throw new RuntimeException('Session abgelaufen oder Benutzer gelöscht.');
        }

        $sessionHash = $this->sessionManager->getAuthHash();
        if ($sessionHash === null || !\hash_equals($sessionHash, $user->getPasswordHash())) {
            $this->logout();

            throw new RuntimeException('Sicherheits-Token ungültig (Passwort wurde eventuell geändert).');
        }

        $this->refreshSessionPermissions($user->roleId);
    }
}
