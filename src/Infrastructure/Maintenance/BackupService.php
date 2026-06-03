<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;

/**
 * Service für die Erstellung, Verwaltung und Wiederherstellung von System-Backups.
 * Handhabt die automatisierte Ausführung, sowie Datei- und Datenbankdumps.
 *
 * Path: src/Infrastructure/Maintenance/BackupService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class BackupService
{
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Generiert ein synchronisiertes Datei- und Datenbank-Abbild eines Zielbereichs im Backup-Ordner.
     * Erzeugt Zeitstempel-Ordner und exportiert JSON-formatierte Rohdaten-Dumps.
     *
     * @param string $target Der zu sichernde Konfigurationsbereich.
     *
     * @return string Relativer Pfad zum erstellten Backup-Ordner.
     */
    public function createBackup(string $target): string
    {
        $timestamp = \date('Ymd-His');
        $root      = \rtrim((string) $this->config->get('root_path'), '/\\');
        $prefix    = \ltrim((string) $this->config->get('storage_path_prefix'), '/\\');
        $subFolder = $this->config->get('backup_settings')['sub_folder'] ?? 'backup';

        $backupPath = $root . '/' . $prefix . $subFolder . '/' . $timestamp;

        if (! \is_dir($backupPath)) {
            \mkdir($backupPath, 0o777, true);
        }

        $jsonFlags     = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        $storageConfig = $this->config->get('storage_config', []);

        // Backup für "alles" oder ein spezifisches Ziel
        if (! isset($storageConfig[$target])) {
            $keysToBackup = ['permits', 'users', 'groups', 'vouchers', 'pending_verification', 'verified_pending', 'magic_links'];
            foreach ($keysToBackup as $key) {
                if (! isset($storageConfig[$key])) {
                    continue;
                }

                $path = $root . '/' . $prefix . $storageConfig[$key]['file'];
                if (\file_exists($path)) {
                    $data = \json_decode((string) \file_get_contents($path), true) ?? [];
                    \file_put_contents($backupPath . "/{$key}_file.json", \json_encode($data, $jsonFlags));
                }
            }

            return $prefix . $subFolder . '/' . $timestamp;
        }

        // Zielspezifisches Backup
        $jsonData = $this->loadRawJson($target);
        if ($jsonData !== []) {
            \file_put_contents($backupPath . "/{$target}_file.json", \json_encode($jsonData, $jsonFlags));
        }

        if ($this->pdo instanceof \PDO) {
            $sqlData = $this->loadRawSql($target);
            if ($sqlData !== []) {
                \file_put_contents($backupPath . "/{$target}_sql.json", \json_encode($sqlData, $jsonFlags));
            }
        }

        return $prefix . $subFolder . '/' . $timestamp;
    }

    /**
     * Scannt das Backup-Verzeichnis und listet alle verfügbaren Backup-Stände und deren Dateiinhalte auf.
     * Listet alle verfügbaren Backup-Ordner sortiert nach Datum (neuere zuerst).
     *
     * @return array<string, array<int, string>> Absteigend sortiertes Array (neueste zuerst) von Datei-Listen.
     */
    public function listBackups(): array
    {
        // BUGFIX: Nutzt jetzt den konfigurieren Sub-Ordner!
        $subFolder  = $this->config->get('backup_settings')['sub_folder'] ?? 'backup';
        $backupPath = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $subFolder;

        if (! \is_dir($backupPath)) {
            return [];
        }

        $folders = \array_diff(\scandir($backupPath), ['.', '..']);
        $result  = [];
        foreach ($folders as $folder) {
            $fullPath = $backupPath . '/' . $folder;
            if (! \is_dir($fullPath)) {
                continue;
            }

            $files           = \array_diff(\scandir($fullPath), ['.', '..']);
            $result[$folder] = \array_values($files);
        }
        \krsort($result);

        return $result;
    }

    /**
     * Ruft die Daten eines spezifischen Backups ab.
     *
     * @param string $timestamp Der Zeitstempel (Ordnername) des Backups.
     * @param string $target    Der Schlüssel des Speicherbereichs.
     *
     * @return array|null Die Backup-Daten oder null, wenn nicht gefunden.
     */
    public function getBackupData(string $timestamp, string $target): ?array
    {
        $root       = $this->config->get('root_path');
        $backupBase = $root . '/' . $this->config->get('storage_path_prefix') . 'backup/' . $timestamp;

        $backupFile = $backupBase . "/{$target}_file.json";
        if (! \file_exists($backupFile)) {
            $backupFile = $backupBase . "/{$target}_sql.json";
        }

        if (! \file_exists($backupFile)) {
            return null;
        }

        return \json_decode((string) \file_get_contents($backupFile), true);
    }

    /**
     * Überwacht und steuert automatisierte Backup-Intervalle im Hintergrund.
     * Prüft anhand eines Timestamps in storage/logs/last_auto_backup.txt, ob ein konfiguriertes Intervall abgelaufen ist,
     * stößt die Sicherung an und rotiert alte Backups (Retention Rate) aus.
     */
    public function checkAutoBackup(): void
    {
        $cfg = $this->config->get('backup_settings', []);

        // Ist Auto-Backup überhaupt aktiviert?
        if (! ($cfg['enabled'] ?? false)) {
            return;
        }

        // Intervall von Stunden in Sekunden umrechnen (Standard: 24h)
        $interval = (int) ($cfg['interval_hours'] ?? 24) * 3600;

        // Pfad in den /storage/logs/ Ordner setzen!
        $logDir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/storage/logs';
        if (! \is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $stateFile  = $logDir . '/last_auto_backup.txt';
        $lastBackup = \file_exists($stateFile) ? (int) \file_get_contents($stateFile) : 0;
        $now        = \time();

        // Prüfen, ob das Intervall abgelaufen ist
        if (($now - $lastBackup) >= $interval) {
            try {
                // BUGFIX: Ziel 'auto_maintenance' übergeben, damit alles gesichert wird
                $this->createBackup('auto_maintenance');

                // Zeitstempel aktualisieren
                \file_put_contents($stateFile, (string) $now);

                // Veraltete Backups löschen
                $this->rotateBackups((int) ($cfg['max_backups'] ?? 10));
            } catch (\Throwable $e) {
                \error_log('Auto-Backup fehlgeschlagen: ' . $e->getMessage());
            }
        }
    }

    /**
     * Rotiert Backup-Ordner basierend auf der maximal zulässigen Anzahl im System (FIFO-Verfahren).
     *
     * @param int $max Die Obergrenze für aufzubewahrende Backups (z.B. 10).
     */
    private function rotateBackups(int $max): void
    {
        $root       = $this->config->get('root_path');
        $prefix     = $this->config->get('storage_path_prefix');
        $backupPath = $root . '/' . $prefix . 'backup';

        if (! \is_dir($backupPath)) {
            return;
        }

        $folders   = \array_diff(\scandir($backupPath), ['.', '..']);
        $fullPaths = [];
        foreach ($folders as $f) {
            if (! \is_dir($backupPath . '/' . $f)) {
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
        if (! \is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            \is_dir("$dir/$file") ? $this->recursiveDelete("$dir/$file") : \unlink("$dir/$file");
        }
        \rmdir($dir);
    }

    /**
     * Hilfsmethoden für reine Lese-Dumps (SRP: BackupService darf Rohdaten lesen)
     *
     * Liest die physischen, rohen JSON-Inhalte einer Systemkomponente aus.
     * Robust gegen fehlende 'file'-Keys (bei reinen MySQL-Configs).
     *
     * @param string $key Speicher-Key aus der Konfiguration.
     *
     * @return array<string, mixed> Ungefiltertes Datenarray.
     */
    private function loadRawJson(string $key): array
    {
        $cfg = $this->config->get('storage_config')[$key];
        if (! isset($cfg['file'])) {
            return [];
        }
        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

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
        $cfg = $this->config->get('storage_config')[$key];
        if (! $this->pdo instanceof \PDO) {
            return [];
        }

        try {
            $tableName = $cfg['table'];
            $stmt      = $this->pdo->query("SELECT * FROM `$tableName`");
            $rows      = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $res       = [];
            $idField   = match ($key) {
                'users', 'groups', 'mail_log', 'mail_queue', 'vouchers_archive' => 'id',
                'magic_links', 'pending_verification', 'verified_pending'       => 'token',
                default                                                         => 'code'
            };
            foreach ($rows as $r) {
                if (isset($r['data']) && \is_string($r['data'])) {
                    $r['data'] = \json_decode($r['data'], true);
                }
                if (isset($r['permissions']) && \is_string($r['permissions'])) {
                    $r['permissions'] = \json_decode($r['permissions'], true);
                }
                $res[$r[$idField]] = $r;
            }

            return $res;
        } catch (\Exception $e) {
            return [];
        }
    }
}
