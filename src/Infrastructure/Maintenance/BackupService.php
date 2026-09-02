<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Utils\ClockInterface;
use Exception;
use Override;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Service für die Erstellung, Verwaltung und Wiederherstellung von System-Backups.
 * Handhabt die automatisierte Ausführung, sowie Datei- und Datenbankdumps.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class BackupService implements BackupServiceInterface
{
    private string $backupDir;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
        $root = $this->config->get('root_path', '');
        $subFolder = $this->config->get('backup_settings')['sub_folder'] ?? 'backups';
        $this->backupDir = \rtrim($root, '/\\') . '/storage/' . $subFolder;

        if (!\is_dir($this->backupDir)) {
            \mkdir($this->backupDir, 0o755, true);
        }

        $htaccessPath = $this->backupDir . '/.htaccess';
        if (!\file_exists($htaccessPath)) {
            \file_put_contents($htaccessPath, "Order allow,deny\nDeny from all\n");
        }
    }

    #[Override]
    public function getBackupData(string $timestamp, string $target): ?array
    {
        throw new Exception('Not implemented');
    }

    public function runCronBackup(): void
    {
        $this->createBackup('all');
    }

    public function createBackup(string $target = 'all'): string
    {
        $storageConfig = $this->config->get('storage_config', []);
        $tablesToBackup = [];

        if ($target === 'all') {
            foreach ($storageConfig as $cfg) {
                if (isset($cfg['table'])) {
                    $tablesToBackup[] = $cfg['table'];
                }
            }
        } elseif (isset($storageConfig[$target]['table'])) {
            $tablesToBackup[] = $storageConfig[$target]['table'];
        } else {
            throw new RuntimeException("Unbekanntes Backup-Ziel: {$target}");
        }

        $backupData = [
            'timestamp' => $this->clock->nowAsString(),
            'target' => $target,
            'tables' => [],
        ];

        foreach ($tablesToBackup as $table) {
            $stmt = $this->pdo->query("SELECT * FROM `$table`");
            if ($stmt !== false) {
                $backupData['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $json = \json_encode($backupData, \JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('JSON encode Fehler beim Backup.');
        }

        $filename = 'backup_' . $this->clock->now()->format('Ymd_His') . '_' . $target . '.zip';
        $filepath = $this->backupDir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Konnte ZIP-Datei nicht erstellen: $filepath");
        }

        $zip->addFromString('data.json', $json);
        $zip->setArchiveComment(\json_encode(['target' => $target, 'tables' => \array_keys($backupData['tables'])]));

        $backupCfg = $this->config->get('backup_settings', []);
        if (!empty($backupCfg['zip_password'])) {
            $zip->setPassword($backupCfg['zip_password']);
            $zip->setEncryptionName('data.json', ZipArchive::EM_AES_256);
        }
        $zip->close();

        // FTP Offsite Backup
        if (($backupCfg['ftp']['enabled'] ?? false) === true) {
            $this->uploadToFtp($filepath, $filename, $backupCfg['ftp']);
        }

        $this->rotateBackups((int) ($backupCfg['max_backups'] ?? 15));

        return $filename;
    }

    public function restoreBackup(string $filename, int $mode, string $target = 'all'): void
    {
        $filepath = $this->backupDir . '/' . \basename($filename);
        if (!\file_exists($filepath)) {
            throw new RuntimeException('Backup-Datei nicht gefunden.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new RuntimeException('Konnte ZIP-Backup nicht öffnen.');
        }

        $backupCfg = $this->config->get('backup_settings', []);
        if (!empty($backupCfg['zip_password'])) {
            $zip->setPassword($backupCfg['zip_password']);
        }

        $json = $zip->getFromName('data.json');
        $zip->close();

        if ($json === false) {
            throw new RuntimeException('Fehler beim Entschlüsseln. Falsches Passwort?');
        }

        $data = \json_decode($json, true);
        if (!isset($data['tables']) || !\is_array($data['tables'])) {
            throw new RuntimeException('Ungültiges Backup-Format.');
        }

        $storageConfig = $this->config->get('storage_config', []);
        $targetTable = $target !== 'all' && isset($storageConfig[$target]['table']) ? $storageConfig[$target]['table'] : null;

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            foreach ($data['tables'] as $table => $rows) {
                if ($targetTable !== null && $table !== $targetTable) {
                    continue;
                }
                $this->restoreTableData($table, $rows, $mode);
            }

            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            throw clone $e;
        }
    }

    private function restoreTableData(string $table, array $rows, int $mode): void
    {
        if (empty($rows)) {
            if ($mode === 2) {
                $this->pdo->exec("TRUNCATE TABLE `$table`");
            }

            return;
        }

        $primaryKeys = $this->getPrimaryKeys($table);

        if ($mode === 2) {
            if (\count($primaryKeys) === 1) {
                $pk = $primaryKeys[0];
                $keptIds = \array_column($rows, $pk);
                if (!empty($keptIds)) {
                    $in = \str_repeat('?,', \count($keptIds) - 1) . '?';
                    $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE `$pk` NOT IN ($in)");
                    $stmt->execute($keptIds);
                } else {
                    $this->pdo->exec("TRUNCATE TABLE `$table`");
                }
            } else {
                $this->pdo->exec("TRUNCATE TABLE `$table`"); // Fallback falls kein eindeutiger PK
            }
        }

        $columns = \array_keys($rows[0]);
        $colNames = \implode(', ', \array_map(fn ($col) => "`$col`", $columns));
        $placeholders = \implode(', ', \array_map(fn ($col) => ":$col", $columns));

        if ($mode === 3) {
            $sql = "INSERT IGNORE INTO `$table` ($colNames) VALUES ($placeholders)";
        } else {
            $updateCols = \implode(', ', \array_map(fn ($col) => "`$col` = VALUES(`$col`)", $columns));
            $sql = "INSERT INTO `$table` ($colNames) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateCols";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private function getPrimaryKeys(string $table): array
    {
        $stmt = $this->pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");

        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    public function listBackups(): array
    {
        if (!\is_dir($this->backupDir)) {
            return [];
        }

        $files = \array_diff(\scandir($this->backupDir), ['.', '..', '.htaccess']);
        $backups = [];

        foreach ($files as $file) {
            if (!\str_ends_with($file, '.zip')) {
                continue;
            }
            $path = $this->backupDir . '/' . $file;

            $zip = new ZipArchive();
            $meta = [];
            if ($zip->open($path) === true) {
                $comment = $zip->getArchiveComment();
                if ($comment) {
                    $meta = \json_decode($comment, true) ?? [];
                }
                $zip->close();
            }

            $backups[] = [
                'filename' => $file,
                'size' => \filesize($path),
                'date' => \filemtime($path),
                'target' => $meta['target'] ?? 'Unbekannt',
                'tables' => $meta['tables'] ?? [],
            ];
        }

        \usort($backups, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    private function rotateBackups(int $max): void
    {
        $backups = $this->listBackups();
        if (\count($backups) <= $max) {
            return;
        }

        $toDelete = \array_slice($backups, $max);
        foreach ($toDelete as $b) {
            @\unlink($this->backupDir . '/' . $b['filename']);
        }
    }

    private function uploadToFtp(string $filepath, string $filename, array $ftpCfg): void
    {
        if (!\function_exists('ftp_connect')) {
            \error_log('FTP Upload übersprungen: PHP FTP-Erweiterung fehlt.');

            return;
        }

        $timeout = 60;
        $connId = ($ftpCfg['ssl'] ?? false)
            ? @\ftp_ssl_connect($ftpCfg['host'], (int) $ftpCfg['port'], $timeout)
            : @\ftp_connect($ftpCfg['host'], (int) $ftpCfg['port'], $timeout);

        if (!$connId || !@\ftp_login($connId, $ftpCfg['user'], $ftpCfg['pass'])) {
            \error_log('Off-Site Backup fehlgeschlagen: FTP Login-Fehler.');

            return;
        }

        \ftp_pasv($connId, true);

        $path = \rtrim($ftpCfg['path'] ?? '', '/\\') . '/';
        foreach (\explode('/', \trim($path, '/')) as $part) {
            if ($part !== '' && !@\ftp_chdir($connId, $part)) {
                \ftp_mkdir($connId, $part);
                \ftp_chdir($connId, $part);
            }
        }

        if (!@\ftp_put($connId, $filename, $filepath, \FTP_BINARY)) {
            \error_log('Off-Site Backup fehlgeschlagen: Upload verweigert.');
        }
        \ftp_close($connId);
    }
}
