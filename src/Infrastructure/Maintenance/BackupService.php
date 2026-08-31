<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Infrastructure\Storage\SafeJsonWriterTrait;
use Exception;
use PDO;

/**
 * Service für die Erstellung, Verwaltung und Wiederherstellung von System-Backups.
 * Handhabt die automatisierte Ausführung, sowie Datei- und Datenbankdumps.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class BackupService implements BackupServiceInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ?PDO $pdo,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    // --- Public API ---

    /**
     * Generiert ein Datenbank-Abbild eines Zielbereichs im Backup-Ordner.
     * Erzeugt Zeitstempel-Ordner und exportiert JSON-formatierte Rohdaten-Dumps direkt aus MySQL.
     *
     * @param string $target Der zu sichernde Konfigurationsbereich.
     *
     * @return string Relativer Pfad zum erstellten Backup-Ordner.
     */
    public function createBackup(string $target): string
    {
        $timestamp = $this->clock->now()->format('Ymd-His');
        $subFolder = $this->config->get('backup_settings')['sub_folder'] ?? 'backup';
        $backupPath = $this->config->getStoragePath($subFolder . '/' . $timestamp);

        if (!\is_dir($backupPath)) {
            \mkdir($backupPath, 0o755, true);
        }

        $jsonFlags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        $storageConfig = $this->config->get('storage_config', []);

        // Komplettes Backup aller Tabellen
        if (!isset($storageConfig[$target])) {
            $keysToBackup = \array_keys($storageConfig);

            foreach ($keysToBackup as $key) {
                if (!($this->pdo instanceof PDO) || !isset($storageConfig[$key]['table'])) {
                    continue;
                }

                $sqlData = $this->loadRawSql($key);
                if ($sqlData === []) {
                    continue;
                }
                $this->writeJsonSafely($backupPath . "/{$key}_sql.json", $sqlData, $jsonFlags);
            }

            return $backupPath;
        }

        // Backup eines einzelnen Targets
        if ($this->pdo instanceof PDO) {
            $sqlData = $this->loadRawSql($target);
            if ($sqlData !== []) {
                $this->writeJsonSafely($backupPath . "/{$target}_sql.json", $sqlData, $jsonFlags);
            }
        }

        return $backupPath;
    }

    /**
     * Scannt das Backup-Verzeichnis und listet alle verfügbaren Backup-Stände und deren Dateiinhalte auf.
     * Listet alle verfügbaren Backup-Ordner sortiert nach Datum (neuere zuerst).
     *
     * @return array<string, array<int, string>> Absteigend sortiertes Array (neueste zuerst) von Datei-Listen.
     */
    public function listBackups(): array
    {
        $subFolder = $this->config->get('backup_settings')['sub_folder'] ?? 'backup';
        $backupPath = $this->config->getStoragePath($subFolder);

        if (!\is_dir($backupPath)) {
            return [];
        }

        $folders = \array_diff(\scandir($backupPath) ?: [], ['.', '..']);
        $result = [];

        foreach ($folders as $folder) {
            $fullPath = $backupPath . '/' . $folder;
            if (!\is_dir($fullPath)) {
                continue;
            }

            $files = \array_diff(\scandir($fullPath) ?: [], ['.', '..']);
            $result[$folder] = \array_values($files);
        }

        \krsort($result);

        return $result;
    }

    /**
     * Ruft die Daten eines spezifischen Backups ab.
     *
     * @param string $timestamp Der Zeitstempel (Ordnername) des Backups.
     * @param string $target Der Schlüssel des Speicherbereichs.
     *
     * @return array|null Die Backup-Daten oder null, wenn nicht gefunden.
     */
    public function getBackupData(string $timestamp, string $target): ?array
    {
        $safeTimestamp = \basename($timestamp);
        $safeTarget = \basename($target);
        $backupBase = $this->config->getStoragePath('backup/' . $safeTimestamp);

        $backupFile = $backupBase . "/{$safeTarget}_file.json";
        if (!\file_exists($backupFile)) {
            $backupFile = $backupBase . "/{$safeTarget}_sql.json";
        }

        if (!\file_exists($backupFile)) {
            return null;
        }

        return $this->jsonHelper->read($backupFile);
    }

    /**
     * Wird vom externen Cronjob getriggert. Erstellt ein Backup und rotiert alte Bestände weg.
     */
    public function runCronBackup(): void
    {
        $this->createBackup('auto_cron_backup');

        $max = (int) ($this->config->get('backup_settings')['max_backups'] ?? 15);
        $this->rotateBackups($max);
    }

    // --- Private Core ---

    /**
     * Rotiert Backup-Ordner basierend auf der maximal zulässigen Anzahl im System (FIFO-Verfahren).
     *
     * @param int $max Die Obergrenze für aufzubewahrende Backups (z.B. 10).
     */
    private function rotateBackups(int $max): void
    {
        $backupPath = $this->config->getStoragePath('backup');
        if (!\is_dir($backupPath)) {
            return;
        }

        $folders = \array_diff(\scandir($backupPath) ?: [], ['.', '..']);
        $fullPaths = [];

        foreach ($folders as $f) {
            if (!\is_dir($backupPath . '/' . $f)) {
                continue;
            }
            $fullPaths[$f] = $backupPath . '/' . $f;
        }

        \ksort($fullPaths);

        if (\count($fullPaths) <= $max) {
            return;
        }

        $toDelete = \array_slice($fullPaths, 0, \count($fullPaths) - $max);
        foreach ($toDelete as $dir) {
            $this->recursiveDelete($dir);
        }
    }

    /**
     * Löscht Verzeichnisstrukturen inklusive aller enthaltenen Dateien rekursiv vom Datenträger.
     *
     * @param string $dir Absoluter Pfad zum Ziel-Verzeichnis.
     */
    private function recursiveDelete(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            \is_dir("$dir/$file") ? $this->recursiveDelete("$dir/$file") : \unlink("$dir/$file");
        }

        \rmdir($dir);
    }

    // --- Private Loaders ---

    /**
     * Liest zeilenbasierte Rohdaten direkt aus einer MySQL-Tabelle aus und normalisiert JSON-Felder.
     * Schützt vor "Undefined array key"-Warnings durch Validierung der Primärschlüssel.
     *
     * @param string $key Tabellen-Key aus der storage_config.
     *
     * @return array<string, mixed> Indiziertes Zeilen-Array, gemappt nach Primärschlüssel.
     */
    private function loadRawSql(string $key): array
    {
        $cfg = $this->config->get('storage_config')[$key] ?? null;
        if (!$cfg || !$this->pdo instanceof PDO) {
            return [];
        }

        try {
            $tableName = $cfg['table'];
            $stmt = $this->pdo->query("SELECT * FROM `$tableName`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $res = [];
            $idField = match ($key) {
                // ID-Tabellen (alphabetisch sortiert)
                'mail_log' => 'id',
                'mail_queue' => 'id',
                'roles' => 'id',
                'users' => 'id',
                'vouchers_archive' => 'id',

                // Token-Tabellen (alphabetisch sortiert)
                'magic_links' => 'token',
                'pending_verification' => 'token',
                'verified_pending' => 'token',

                // default
                default => 'code'
            };

            foreach ($rows as $r) {
                if (isset($r['data']) && \is_string($r['data'])) {
                    $r['data'] = $this->jsonHelper->decode($r['data']) ?? [];
                }
                if (isset($r['permissions']) && \is_string($r['permissions'])) {
                    $r['permissions'] = $this->jsonHelper->decode($r['permissions']) ?? [];
                }
                $res[$r[$idField]] = $r;
            }

            return $res;
        } catch (Exception) {
            return [];
        }
    }
}
