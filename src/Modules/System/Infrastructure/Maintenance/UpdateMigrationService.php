<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use Exception;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final readonly class UpdateMigrationService implements UpdateMigrationServiceInterface
{
    public function __construct(
        private ?PDO $pdo,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function runAllPending(): array
    {
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('Fehler: Keine Datenbankverbindung für Migrationen vorhanden.');
        }

        $executed = $this->getExecutedMigrations();
        $migrationsDir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/database/migrations';
        $executedNow = [];

        if (!\is_dir($migrationsDir)) {
            \error_log('Migration: Ordner nicht gefunden: ' . $migrationsDir);

            return $executedNow;
        }

        $files = \glob($migrationsDir . \DIRECTORY_SEPARATOR . '*.sql');

        if ($files === false | $files === []) {
            return $executedNow;
        }

        \sort($files);

        foreach ($files as $file) {
            $version = \basename($file, '.sql');

            if (\in_array($version, $executed, true)) {
                continue;
            }

            try {
                $sql = \file_get_contents($file);

                if ($sql === false | \trim($sql) === '') {
                    throw new RuntimeException("Datei {$version}.sql ist leer oder nicht lesbar.");
                }

                $statements = \array_filter(\array_map(trim(...), \explode(';', $sql)));

                foreach ($statements as $statement) {
                    if ($statement === '') {
                        continue;
                    }

                    try {
                        $this->pdo->exec($statement);
                    } catch (PDOException $e) {
                        $mysqlCode = $e->errorInfo[1] ?? 0;

                        // BUGFIX: 1054 = Unknown column (Wird geworfen, wenn man eine Spalte via ALTER TABLE CHANGE
                        // umbenennen will, sie aber schon umbenannt ist).
                        if (\in_array($mysqlCode, [1050, 1051, 1054, 1060, 1061, 1146], true)) {
                            continue;
                        }

                        throw $e;
                    }
                }

                $this->markAsExecuted($version);
                $executedNow[] = $version;
            } catch (Throwable $e) {
                \error_log("Kritischer Fehler bei SQL-Migration {$version}: " . $e->getMessage());

                throw new RuntimeException("SQL-Migration '{$version}' fehlgeschlagen: " . $e->getMessage(), 0, $e);
            }
        }

        return $executedNow;
    }

    private function getExecutedMigrations(): array
    {
        $cfg = $this->config->get('storage_config')['update_migrations'] ?? null;
        if (!$cfg | !$this->pdo instanceof PDO) {
            return [];
        }

        try {
            $stmt = $this->pdo->query("SELECT `version` FROM `{$cfg['table']}`");

            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException) {
            return [];
        }
    }

    private function markAsExecuted(string $version): void
    {
        $cfg = $this->config->get('storage_config')['update_migrations'] ?? null;
        if (!$cfg | !$this->pdo instanceof PDO) {
            return;
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `{$cfg['table']}` (`id`, `version`, `executed_at`) VALUES (?, ?, ?)");
        $stmt->execute([\uniqid('mig_', true), $version, $now]);
    }

    public function import(array $data): void
    {
        $cfg = $this->config->get('storage_config')['update_migrations'] ?? null;
        if (!$cfg | !$this->pdo instanceof PDO) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("REPLACE INTO `{$cfg['table']}` (id, version, executed_at) VALUES (?, ?, ?)");
            foreach ($data as $id => $item) {
                $stmt->execute([$id, $item['version'] ?? '', $item['executed_at'] ?? '']);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
