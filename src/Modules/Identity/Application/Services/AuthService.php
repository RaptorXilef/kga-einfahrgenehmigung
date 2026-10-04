<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Override;
use Throwable;

/**
 * Service für Sitzungsverwaltung, Seeding und Berechtigungsprüfung von Administratoren.
 * Synchronisiert aktive Benutzersitzungen einmalig pro HTTP-Request live mit der Datenbank,
 * damit Rollen- und Rechteänderungen ohne erneuten Login sofort greifen.
 */
final class AuthService implements AuthorizationInterface
{
    private bool $isSessionSynced = false;

    /**
     * Request-lokaler RAM-Cache für alle Rollen, um mehrfache DB-Abfragen bei hasPermission() zu vermeiden.
     *
     * @var array<string, Role>|null
     */
    private ?array $rolesCache = null;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly AuthSessionInterface $sessionManager,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    #[Override]
    public function logout(): void
    {
        $this->isSessionSynced = false;
        $this->sessionManager->destroy();
        $this->sessionManager->rotateCsrfToken();
    }

    #[Override]
    public function isLoggedIn(): bool
    {
        if (!$this->syncSessionSafely()) {
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
        if (!$this->syncSessionSafely()) {
            return false;
        }

        $uid = $this->sessionManager->getUserId();
        if ($uid === '') {
            return false;
        }

        if (\str_starts_with($uid, 'sys_')) {
            return true;
        }

        $roleId = $this->sessionManager->getAdminGroup();
        $roles = $this->getRoles();

        if (isset($roles[$roleId]) && \in_array('*', $roles[$roleId]->getPermissions(), true)) {
            return true;
        }

        return ($this->sessionManager->getPermissions()[$permission] ?? false) === true;
    }

    #[Override]
    public function refreshSessionPermissions(string $roleId): void
    {
        $this->rolesCache = null;
        $roles = $this->getRoles();
        $rolePerms = isset($roles[$roleId]) ? $roles[$roleId]->getPermissions() : [];
        $structure = $this->config->getArray('structure');

        $compiler = new PermissionCompiler();
        $this->sessionManager->setPermissions($compiler->compile($structure, $rolePerms));
        $this->sessionManager->setAdminRoleName($this->resolveRoleDisplayName($roleId, $roles));
        $this->isSessionSynced = true;
    }

    #[Override]
    public function getUsername(): string
    {
        $this->syncSessionSafely();

        return $this->sessionManager->getAdminUser();
    }

    #[Override]
    public function getUserId(): string
    {
        $this->syncSessionSafely();

        return $this->sessionManager->getUserId();
    }

    #[Override]
    public function getRole(): string
    {
        $this->syncSessionSafely();

        return $this->sessionManager->getAdminGroup();
    }

    #[Override]
    public function getRoleName(): string
    {
        $this->syncSessionSafely();

        $cachedName = $this->sessionManager->getAdminRoleName();
        if ($cachedName !== '') {
            return $cachedName;
        }

        $roleId = $this->sessionManager->getAdminGroup();
        if ($roleId === '' || $roleId === 'guest') {
            return 'Gast';
        }

        try {
            $roles = $this->getRoles();
            $resolved = $this->resolveRoleDisplayName($roleId, $roles);
            $this->sessionManager->setAdminRoleName($resolved);

            return $resolved;
        } catch (Throwable) {
            return \ucfirst(\str_replace('role_', '', $roleId));
        }
    }

    #[Override]
    public function getLastSeenChangelog(): string
    {
        $userId = $this->getUserId();
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

    /**
     * Synchronisiert die aktive Benutzersitzung einmalig pro Request mit der Datenbank.
     * Erkennt gelöschte Konten, geänderte Passwörter, geänderte Rollenzuweisungen
     * sowie aktualisierte Rollen-Berechtigungen in Echtzeit.
     */
    private function syncSessionSafely(): bool
    {
        $userId = $this->sessionManager->getUserId();
        if ($userId === '') {
            return false;
        }

        if (\str_starts_with($userId, 'sys_')) {
            $this->isSessionSynced = true;

            return true;
        }

        if ($this->isSessionSynced) {
            return true;
        }

        try {
            $user = $this->userRepository->findById($userId);
        } catch (Throwable) {
            return false;
        }

        if (!$user instanceof User) {
            $this->logout();

            return false;
        }

        $sessionHash = $this->sessionManager->getAuthHash();
        if ($sessionHash === null || !\hash_equals($sessionHash, $user->getPasswordHash())) {
            $this->logout();

            return false;
        }

        // Aktualisiere Rollen-ID und Benutzernamen in der Session (falls durch Admin geändert)
        $this->sessionManager->setAuthSession(
            $user->id,
            $user->getRoleId(),
            $user->getUsername(),
            $user->getPasswordHash(),
        );

        // Kompiliere die Berechtigungen der aktuellen Rolle frisch aus der Datenbank
        $this->refreshSessionPermissions($user->getRoleId());

        return true;
    }

    /**
     * @return array<string, Role>
     */
    private function getRoles(): array
    {
        if ($this->rolesCache === null) {
            try {
                $this->rolesCache = $this->roleRepository->loadAll();
            } catch (Throwable) {
                $this->rolesCache = [];
            }
        }

        return $this->rolesCache;
    }

    /**
     * @param array<string, Role> $roles
     */
    private function resolveRoleDisplayName(string $roleId, array $roles): string
    {
        if (isset($roles[$roleId])) {
            return $roles[$roleId]->getName();
        }

        if ($roleId === 'admin' && isset($roles['role_admin'])) {
            return $roles['role_admin']->getName();
        }

        if ($roleId === 'admin' || \str_starts_with($this->sessionManager->getUserId(), 'sys_')) {
            return 'Administrator';
        }

        return \ucfirst(\str_replace('role_', '', $roleId));
    }

    private function cleanupOrphanedPermissions(): void
    {
        $roles = $this->getRoles();
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

            $role->updatePermissions($cleanedPerms);
            $this->roleRepository->save($role);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $this->rolesCache = null;
        \error_log('Bootstrap: Veraltete Berechtigungen (Orphaned Permissions) wurden erfolgreich bereinigt.');
    }

    private function initDefaultRolesAndUsers(): void
    {
        $currentRoles = $this->getRoles();

        if ($currentRoles === []) {
            \error_log('Bootstrap: Initialisiere Standard-Rollen.');
            foreach ($this->getDefaultRoles() as $role) {
                $this->roleRepository->save($role);
            }
            $this->rolesCache = null;
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
