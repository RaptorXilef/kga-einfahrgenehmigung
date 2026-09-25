<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use Override;
use PDO;
use PDOException;

final readonly class StorageBootstrapper implements StorageBootstrapperInterface
{
    public function __construct(
        private ?PDO $pdo,
        private ConfigInterface $config,
        private AuthorizationInterface $auth,
        private UpdateMigrationServiceInterface $migrationService,
    ) {
    }

    #[Override]
    public function bootstrap(): void
    {
        if ($this->pdo instanceof PDO) {
            try {
                $this->pdo->exec('CREATE TABLE IF NOT EXISTS `update_migrations` (
                    `id` VARCHAR(50) PRIMARY KEY,
                    `version` VARCHAR(50) NOT NULL,
                    `executed_at` DATETIME NOT NULL,
                    UNIQUE KEY `idx_version` (`version`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

                $this->migrationService->runAllPending();
            } catch (PDOException $e) {
                \error_log('Bootstrap: Fehler bei der Ausführung der Migrationen: ' . $e->getMessage());
            }
        }

        $this->auth->bootstrapDefaultIdentityData();
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
}
