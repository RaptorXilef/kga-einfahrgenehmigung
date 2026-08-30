<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use App\Core\Entity\Role;
use App\Core\Entity\User;
use PDO;
use PDOException;

/**
 * Bootstrapper für die Initialisierung der Speicher-Infrastruktur.
 * Stellt sicher, dass Datenbanktabellen oder JSON-Dateien beim Start vorhanden sind,
 * und führt bei Bedarf initiale Auto-Setups aus.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class StorageBootstrapper implements StorageBootstrapperInterface
{
    public function __construct(
        private ?PDO $pdo,
        private ConfigInterface $config,
        private RoleRepositoryInterface $roleRepository,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Öffentlicher Einstieg
     */
    public function bootstrap(): void
    {
        if ($this->pdo instanceof PDO) {
            $schema = $this->config->get('db_schema', []);
            foreach ($schema as $tableName => $sql) {
                try {
                    $this->pdo->exec($sql);
                } catch (PDOException $e) {
                    \error_log("Bootstrap: Fehler beim Erstellen der Tabelle '$tableName': " . $e->getMessage());
                }
            }
        }

        $this->initDefaultRolesAndUsers();
        $this->cleanupOrphanedPermissions();
        $this->ensureStorageSecurity();
    }

    private function ensureStorageSecurity(): void
    {
        $storageDir = \rtrim($this->config->getStoragePath(''), '/\\');
        $htaccessPath = $storageDir . '/.htaccess';

        if (!\is_dir($storageDir)) {
            @\mkdir($storageDir, 0o755, true);
        }

        $expectedContent = "# AUTO-GENERATED SECURITY FILE\n" .
            "# Verhindert jeglichen direkten HTTP-Zugriff auf Logs und Backups.\n" .
            "Order Allow,Deny\n" .
            "Deny from all\n\n" .
            "Options -Indexes\n";

        if (\file_exists($htaccessPath) && \file_get_contents($htaccessPath) === $expectedContent) {
            return;
        }

        @\file_put_contents($htaccessPath, $expectedContent, \LOCK_EX);
    }

    /**
     * Entfernt verwaiste Rechte-Keys aus den Gruppen, die in der aktuellen
     * permissions.php nicht mehr existieren (Schatten-Rechte).
     */
    private function cleanupOrphanedPermissions(): void
    {
        $roles = $this->roleRepository->loadAll();
        if ($roles === []) {
            return;
        }

        $validKeys = \array_keys($this->config->get('permissions', []));
        $validKeys[] = '*';

        $changed = false;
        foreach ($roles as $id => $role) {
            $originalCount = \count($role->permissions);
            $cleanedPerms = [];

            foreach ($role->permissions as $perm) {
                $basePerm = \ltrim($perm, '-');
                if (!\in_array($basePerm, $validKeys, true)) {
                    continue;
                }
                $cleanedPerms[] = $perm;
            }

            if (\count($cleanedPerms) === $originalCount) {
                continue;
            }

            $roles[$id] = new Role(
                $role->id,
                $role->name,
                \array_values($cleanedPerms),
            );
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        \error_log('Bootstrap: Veraltete Berechtigungen (Orphaned Permissions) wurden erfolgreich bereinigt.');
        $this->roleRepository->saveAll($roles);
    }

    /**
     * Initialisiert Standard-Gruppen und einen Standard-Admin,
     * falls das System (Datenbank oder JSON) komplett leer ist.
     */
    private function initDefaultRolesAndUsers(): void
    {
        $currentUsers = $this->userRepository->loadAll();
        $currentRoles = $this->roleRepository->loadAll();

        if ($currentRoles === []) {
            \error_log('Bootstrap: Initialisiere Standard-Rollen.');
            $this->roleRepository->saveAll($this->getDefaultRoles());
        }

        if ($currentUsers !== []) {
            return;
        }

        \error_log('Bootstrap: Initialisiere Standard-Admin.');
        $this->userRepository->saveAll($this->getDefaultUsers());
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
                'role_admin', // Zuweisung zur Admin-Rolle
                '$2y$12$DHelEqSuvcbbGPYWqnIrIOfs/PYaMVfyahWHkW.aRM43syMd5ASoW',
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
                'privacy.finance.view', 'privacy.emails.view',
                'permits.view', 'permits.print', 'permits.suspend', 'permits.create',
                'finance.view', 'finance.mark_paid', 'finance.export',
                'stats.view', 'stats.charts', 'stats.ranking',
                'vouchers.view', 'vouchers.suspend', 'vouchers.delete', 'vouchers.create',
                'template.manage', 'template.std_7', 'template.std_14', 'template.std_30',
                'template.perm_3', 'template.perm_6', 'template.perm_9', 'template.perm_12',
                'template.custom_std', 'template.custom_perm',
            ]),
            'role_support' => new Role('role_support', 'Sachbearbeitung', [
                'admin.access',
                'privacy.emails.view',
                'permits.view', 'permits.print', 'permits.create',
                'finance.view',
                'vouchers.view', 'vouchers.suspend', 'vouchers.create',
                'system.logs.view',
                'template.manage', 'template.std_7', 'template.std_14', 'template.std_30',
                'template.perm_3', 'template.perm_6', 'template.perm_9', 'template.perm_12',
                'template.custom_std', 'template.custom_perm',
            ]),
            'role_inspector' => new Role('role_inspector', 'Prüfer vor Ort', [
                'admin.access', 'permits.view',
            ]),
        ];
    }
}
