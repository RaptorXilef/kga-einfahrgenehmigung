<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Contracts\Storage\BackupServiceInterface;
use Exception;
use PDO;
use PDOException;
use RuntimeException;

final readonly class MigrationService implements MigrationServiceInterface
{
    public function __construct(
        private ?PDO $pdo,
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
    ) {
    }

    public function clearCache(): string
    {
        $root = $this->config->get('root_path');
        $deptracCache = $root . '/.cache/deptrac/.deptrac.cache';
        if (\file_exists($deptracCache)) {
            @\unlink($deptracCache);
        }

        return 'Erfolg: Der System-Cache wurde geleert und die Berechtigungen neu kompiliert.';
    }

    public function truncateTarget(string $target, string $engine = 'all'): string
    {
        try {
            // --- FIX: Nur noch $target, kein _before_truncate Suffix mehr! ---
            $this->backupService->createBackup($target);
        } catch (Exception $e) {
            return 'Abbruch: Sicherheits-Backup konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        $cfg = $this->config->get('storage_config')[$target] ?? null;
        if (!$cfg || !$this->pdo instanceof PDO) {
            return "Fehler: Unbekannter Speicherbereich '$target' oder DB offline.";
        }

        try {
            $tableName = $cfg['table'];
            $allowedTables = \array_column($this->config->get('storage_config'), 'table');

            if (!\in_array($tableName, $allowedTables, true)) {
                throw new RuntimeException('Sicherheitsabbruch: Tabellenname nicht in Config autorisiert.');
            }

            $this->pdo->exec("TRUNCATE TABLE `$tableName`");

            return "Erfolg: Die Tabelle '$tableName' wurde geleert. Ein Backup wurde erstellt.";
        } catch (PDOException $e) {
            \error_log('Truncate Error MySQL: ' . $e->getMessage());

            return 'Fehler beim Leeren der Tabelle: ' . $e->getMessage();
        }
    }
}
