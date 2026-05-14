<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * @file src/Core/Service/MigrationService.php
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Storage\JsonStorage; // FIX: Fehlte!
use App\Infrastructure\Storage\MySqlStorage;

final readonly class MigrationService
{
    public function __construct(
        private ConfigInterface $config,
        private ?\PDO $pdo,
        private PermitService $permitService,
        private AuthService $authService,
        private VoucherService $voucherService,
        private MagicLinkService $magicLinkService,
        private MailServiceInterface $mailService,
    ) {
    }

    public function execute(string $target, string $action): string
    {
        // 1. Sicherheit: Backup erstellen, bevor wir IRGENDWAS anfassen
        try {
            $backupFolder = $this->createAutoBackup($target);
        } catch (\Exception $e) {
            return 'Abbruch: Backup konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        // 2. MySQL Check
        if (! $this->pdo && \str_contains($action, 'mysql')) {
            return 'Fehler: MySQL-Server ist nicht erreichbar.';
        }

        // 3. Eigentliche Aktion ausführen
        $result = match ($action) {
            'json_to_mysql' => $this->migrateJsonToSql($target),
            'mysql_to_json' => $this->migrateSqlToJson($target),
            'sync'          => $this->syncBoth($target),
            default         => 'Fehler: Unbekannte Aktion.'
        };

        return "Backup erstellt in $backupFolder. <br>" . $result;
    }

    /**
     * Erstellt einen timestamped Ordner und sichert beide Datenquellen als JSON
     */
    private function createAutoBackup(string $target): string
    {
        $timestamp  = \date('Ymd-His'); // YYYYMMDD-HHmmss
        $root       = $this->config->get('root_path');
        $backupPath = $root . '/' . $this->config->get('storage_path_prefix') . 'backup/' . $timestamp;

        if (! \is_dir($backupPath)) {
            \mkdir($backupPath, 0o777, true);
        }

        // Flags: 128 (PRETTY) + 64 (UNESCAPED_SLASHES) + 256 (UNESCAPED_UNICODE) = 448
        $jsonFlags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

        $jsonData = $this->loadRawJson($target);
        if (! empty($jsonData)) {
            \file_put_contents(
                $backupPath . "/{$target}_file.json",
                \json_encode($jsonData, $jsonFlags),
            );
        }

        // --- B. MySQL-Quelle sichern (falls erreichbar) ---
        if ($this->pdo) {
            $sqlData = $this->loadRawSql($target);
            if (! empty($sqlData)) {
                \file_put_contents($backupPath . "/{$target}_sql.json", \json_encode($sqlData, $jsonFlags));
            }
        }

        return $this->config->get('storage_path_prefix') . 'backup/' . $timestamp;
    }

    private function migrateSqlToJson(string $target): string
    {
        if ($target === 'permits') {
            // Sonderbehandlung für Genehmigungen wegen Entity-Mapping
            $json  = new JsonStorage($this->getFilePath('permits'));
            $sql   = new MySqlStorage($this->pdo);
            $count = $sql->migrateTo($json);

            return "$count Genehmigungen nach JSON exportiert.";
        }

        $data = $this->loadRawSql($target);
        if (empty($data)) {
            return "Keine Daten in MySQL-Quelle für $target gefunden.";
        }

        $this->saveToJson($target, $data);

        return \count($data) . ' Datensätze von MySQL nach JSON kopiert.';
    }

    private function migrateJsonToSql(string $target): string
    {
        if ($target === 'permits') {
            // Sonderbehandlung für Genehmigungen wegen Entity-Mapping
            $json  = new JsonStorage($this->getFilePath('permits'));
            $sql   = new MySqlStorage($this->pdo);
            $count = $json->migrateTo($sql);

            return "$count Genehmigungen nach MySQL verschoben.";
        }

        $data = $this->loadRawJson($target);
        if (empty($data)) {
            return "Keine Daten in JSON-Quelle für $target gefunden.";
        }

        $this->saveToSql($target, $data);

        return \count($data) . ' Datensätze von JSON nach MySQL kopiert.';
    }

    private function syncBoth(string $target): string
    {
        // 1. Daten aus beiden Quellen laden
        $jsonData = $this->loadRawJson($target);
        $sqlData  = $this->loadRawSql($target);

        // 2. Zusammenführen (SQL Daten haben bei gleichen Keys Vorrang)
        $merged = \array_replace_recursive($jsonData, $sqlData);

        // 3. Den neuen Gesamtbestand in beide Welten schreiben
        $this->saveToJson($target, $merged);
        $this->saveToSql($target, $merged);

        return "Erfolg: '$target' synchronisiert. Gesamtbestand: " . \count($merged);
    }

    // --- Helfer für direkten Zugriff ohne Rücksicht auf die aktuelle Config-Einstellung ---

    private function loadRawJson(string $key): array
    {
        $cfg  = $this->config->get('storage_config')[$key];
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    private function saveToJson(string $key, array $data): void
    {
        $cfg  = $this->config->get('storage_config')[$key];
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    private function loadRawSql(string $key): array
    {
        $cfg = $this->config->get('storage_config')[$key];
        if (! $this->pdo) {
            return [];
        }

        $stmt = $this->pdo->query("SELECT * FROM {$cfg['table']}");
        $rows = $stmt->fetchAll();
        $res  = [];

        // Dynamische Bestimmung des ID-Feldes
        $idField = match ($key) {
            'users'                                                   => 'username',
            'mail_log'                                                => 'id',
            'magic_links', 'pending_verification', 'verified_pending' => 'token',
            default                                                   => 'code'
        };

        foreach ($rows as $r) {
            if (isset($r['data'])) {
                $r['data'] = \json_decode((string) $r['data'], true);
            }
            $res[$r[$idField]] = $r;
        }

        return $res;
    }

    /**
     * Schreibt Rohdaten (Arrays) in die SQL-Tabellen
     */
    private function saveToSql(string $key, array $data): void
    {

        match ($key) {
            'users'                => $this->authService->saveUsers($data),
            'vouchers'             => $this->voucherService->saveVouchers($data),
            'magic_links'          => $this->magicLinkService->saveLinks($data),
            'mail_log'             => $this->mailService->saveLogs($data),
            'pending_verification' => $this->permitService->savePendingData('pending_verification', $data),
            'verified_pending'     => $this->permitService->savePendingData('verified_pending', $data),
            'permits'              => $this->migratePermitsToSql($data),
            default                => null
        };
    }

    // Kleine Hilfsmethoden, um saveToSql sauber zu halten:

    private function migratePermitsToSql(array $data): void
    {
        if (! $this->pdo) {
            return;
        }
        $storage = new MySqlStorage($this->pdo);
        foreach ($data as $item) {
            $storage->save($this->permitService->arrayToEntity($item));
        }
    }

    private function getFilePath(string $key): string
    {
        $cfg = $this->config->get('storage_config')[$key];

        return $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
    }

    /**
     * Listet alle verfügbaren Backup-Ordner sortiert nach Datum (neuere zuerst).
     * @return array<string, array<string>>
     */
    public function listBackups(): array
    {
        $backupPath = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . 'backup';
        if (! \is_dir($backupPath)) {
            return [];
        }

        $folders = \array_diff(\scandir($backupPath), ['.', '..']);
        $result  = [];

        foreach ($folders as $folder) {
            $fullPath = $backupPath . '/' . $folder;
            if (\is_dir($fullPath)) {
                // Prüfen, welche Dateien im Backup liegen
                $files           = \array_diff(\scandir($fullPath), ['.', '..']);
                $result[$folder] = \array_values($files);
            }
        }

        \krsort($result); // Neueste Backups oben

        return $result;
    }

    /**
     * Stellt einen Datentyp aus einem Backup wieder her.
     */
    public function restore(string $timestamp, string $target): string
    {
        // 1. Sicherheits-Check: Erst aktuelles Backup ziehen!
        $this->createAutoBackup($target . '_before_restore');

        $root       = $this->config->get('root_path');
        $backupBase = $root . '/' . $this->config->get('storage_path_prefix') . 'backup/' . $timestamp;

        // Wir suchen im Backup-Ordner nach der Datei für das Target
        // Priorität: Wir nehmen die *_file.json (da JSON das universelle Austauschformat ist)
        $backupFile = $backupBase . "/{$target}_file.json";
        if (! \file_exists($backupFile)) {
            $backupFile = $backupBase . "/{$target}_sql.json"; // Fallback auf SQL-Export
        }

        if (! \file_exists($backupFile)) {
            return "Fehler: Keine Backup-Datei für '$target' im Ordner $timestamp gefunden.";
        }

        $data = \json_decode((string) \file_get_contents($backupFile), true);
        if ($data === null) {
            return 'Fehler: Backup-Datei ist beschädigt.';
        }

        // 2. In die aktuell AKTIVE Quelle schreiben (egal ob JSON oder SQL)
        $storageCfg = $this->config->get('storage_config')[$target];

        if ($storageCfg['type'] === 'mysql') {
            $this->saveToSql($target, $data);
            $sourceInfo = 'MySQL-Datenbank';
        } else {
            $this->saveToJson($target, $data);
            $sourceInfo = 'JSON-Datei';
        }

        return "Erfolg: '$target' wurde aus Backup [$timestamp] in $sourceInfo wiederhergestellt.";
    }
}
