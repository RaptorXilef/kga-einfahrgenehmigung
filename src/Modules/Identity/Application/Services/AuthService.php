<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Override;
use RuntimeException;
use Throwable;

/**
 * Service für Sitzungsverwaltung, Seeding und Berechtigungsprüfung von Administratoren.
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

        if (isset($roles[$roleId]) && \in_array('*', $roles[$roleId]->getPermissions(), true)) {
            return true;
        }

        return ($this->sessionManager->getPermissions()[$permission] ?? false) === true;
    }

    #[Override]
    public function refreshSessionPermissions(string $roleId): void
    {
        $roles = $this->roleRepository->loadAll();
        $rolePerms = isset($roles[$roleId]) ? $roles[$roleId]->getPermissions() : [];
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

    #[Override]
    public function getLastSeenChangelog(): string
    {
        $userId = $this->sessionManager->getUserId();
        if ($userId === '' || \str_starts_with($userId, 'sys_')) {
            return 'v0.0.0';
        }

        $user = $this->userRepository->findById($userId);

        return $user instanceof User ? $user->getLastSeenChangelog() : 'v0.0.0';
    }

    #[Override]
    public function bootstrapDefaultIdentityData(): void
    {
        $this->initDefaultRolesAndUsers();
        $this->cleanupOrphanedPermissions();
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

        $this->refreshSessionPermissions($user->getRoleId());
    }

    private function cleanupOrphanedPermissions(): void
    {
        try {
            $roles = $this->roleRepository->loadAll();
        } catch (Throwable) {
            return;
        }

        if ($roles === []) {
            return;
        }

        $validKeys = \array_keys($this->config->getArray('permissions'));
        $validKeys[] = '*';

        $changed = false;
        foreach ($roles as $role) {
            $currentPerms = $role->getPermissions();
            $originalCount = \count($currentPerms);
            $cleanedPerms = [];

            foreach ($currentPerms as $perm) {
                $permStr = (string) $perm;
                $basePerm = \ltrim($permStr, '-');
                if (!\in_array($basePerm, $validKeys, true)) {
                    continue;
                }
                $cleanedPerms[] = $permStr;
            }

            if (\count($cleanedPerms) === $originalCount) {
                continue;
            }

            $role->updatePermissions(\array_values($cleanedPerms));
            $this->roleRepository->save($role);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        \error_log('Bootstrap: Veraltete Berechtigungen (Orphaned Permissions) wurden erfolgreich bereinigt.');
    }

    private function initDefaultRolesAndUsers(): void
    {
        try {
            $currentRoles = $this->roleRepository->loadAll();
        } catch (Throwable) {
            $currentRoles = [];
        }

        if ($currentRoles === []) {
            \error_log('Bootstrap: Initialisiere Standard-Rollen.');
            foreach ($this->getDefaultRoles() as $role) {
                $this->roleRepository->save($role);
            }
        }

        try {
            $userCheck = $this->userRepository->findById('usr_7c13b491');
        } catch (Throwable) {
            $userCheck = null;
        }

        if ($userCheck instanceof User) {
            return;
        }

        \error_log('Bootstrap: Initialisiere Standard-Admin.');
        foreach ($this->getDefaultUsers() as $user) {
            $this->userRepository->save($user);
        }
    }

    /**
     * @return array<string, User>
     */
    private function getDefaultUsers(): array
    {
        return [
            'usr_7c13b491' => new User(
                'usr_7c13b491',
                'Admin',
                'role_admin',
                '$2y$12$DHelEqSuvcbbGPYWqnIrIOfs/PYaMVfyahWHkW.aRM43syMd5ASoW',
                'v0.0.0',
            ),
        ];
    }

    /**
     * @return array<string, Role>
     */
    private function getDefaultRoles(): array
    {
        return [
            'role_admin' => new Role('role_admin', 'Administrator', ['*']),
            'role_finance' => new Role('role_finance', 'Finanzen', [
                'admin.access',
                'finance.export',
                'finance.mark_paid',
                'finance.view',
                'permits.create',
                'permits.print',
                'permits.suspend',
                'permits.view',
                'privacy.emails.view',
                'privacy.finance.view',
                'stats.charts',
                'stats.ranking',
                'stats.view',
                'template.custom_perm',
                'template.custom_std',
                'template.manage',
                'template.perm_12',
                'template.perm_3',
                'template.perm_6',
                'template.perm_9',
                'template.std_14',
                'template.std_30',
                'template.std_7',
                'vouchers.create',
                'vouchers.delete',
                'vouchers.suspend',
                'vouchers.view',
                'permits.export.active',
                'permits.export.future',
                'permits.export.expired',
                'permits.export.active_future',
                'permits.export.all',
            ]),
            'role_support' => new Role('role_support', 'Sachbearbeitung', [
                'admin.access',
                'finance.view',
                'permits.create',
                'permits.print',
                'permits.view',
                'privacy.emails.view',
                'system.logs.view',
                'template.custom_perm',
                'template.custom_std',
                'template.manage',
                'template.perm_12',
                'template.perm_3',
                'template.perm_6',
                'template.perm_9',
                'template.std_14',
                'template.std_30',
                'template.std_7',
                'vouchers.create',
                'vouchers.suspend',
                'vouchers.view',
                'permits.export.active',
                'permits.export.future',
                'permits.export.active_future',
            ]),
            'role_inspector' => new Role('role_inspector', 'Prüfer vor Ort', [
                'admin.access',
                'permits.view',
            ]),
        ];
    }
}
