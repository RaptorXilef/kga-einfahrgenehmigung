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
use App\Infrastructure\Storage\JsonStorage;
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
        $timestamp = \date('Ymd-His'); // YYYYMMDD-HHmmss
        $root      = $this->config->get('root_path');
        $prefix    = $this->config->get('storage_path_prefix');

        // Nutzt jetzt den Namen aus der Config (z.B. 'sql_backup')
        $subFolder = $this->config->get('backup_settings')['sub_folder'] ?? 'backup';

        $backupPath = \rtrim($root, '/\\') . '/' . \ltrim($prefix, '/\\') . $subFolder . '/' . $timestamp;

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

        return $prefix . $subFolder . '/' . $timestamp;
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
        $path = \rtrim($this->config->get('root_path'), '/\\') . '/' . \ltrim($this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    private function saveToJson(string $key, array $data): void
    {
        $cfg  = $this->config->get('storage_config')[$key];
        $path = \rtrim($this->config->get('root_path'), '/\\') . '/' . \ltrim($this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
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
            'users'                                                   => 'id', // Von username auf id geändert!
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
            'mail_log'             => $this->mailService->saveLogs($data), // JETZT SAUBER ÜBER SERVICE
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

    /**
     * Prüft das Zeit-Intervall und führt ggf. ein Backup mit Rotation durch.
     */
    public function checkAutoBackup(): void
    {
        $cfg = $this->config->get('backup_settings');
        if (! ($cfg['enabled'] ?? false)) {
            return;
        }

        $interval  = ($cfg['interval_hours'] ?? 24) * 3600;
        $root      = $this->config->get('root_path');
        $prefix    = $this->config->get('storage_path_prefix');
        $stateFile = $root . $prefix . 'last_auto_backup.txt';

        $lastBackup = \file_exists($stateFile) ? (int) \file_get_contents($stateFile) : 0;

        if (\time() - $lastBackup > $interval) {
            // 1. Neues Backup erstellen
            $this->createAutoBackup('auto_maintenance');
            \file_put_contents($stateFile, (string) \time());

            // 2. Alte Backups löschen (Rotation)
            $this->rotateBackups((int) ($cfg['max_backups'] ?? 10));
        }
    }

    /**
     * Löscht die ältesten Backup-Ordner, wenn das Limit überschritten ist.
     */
    private function rotateBackups(int $max): void
    {
        $root       = $this->config->get('root_path');
        $prefix     = $this->config->get('storage_path_prefix');
        $backupPath = $root . $prefix . 'backup';

        if (! \is_dir($backupPath)) {
            return;
        }

        $folders   = \array_diff(\scandir($backupPath), ['.', '..']);
        $fullPaths = [];
        foreach ($folders as $f) {
            if (\is_dir($backupPath . '/' . $f)) {
                $fullPaths[$f] = $backupPath . '/' . $f;
            }
        }

        \ksort($fullPaths); // Sortiert chronologisch (älteste zuerst)

        if (\count($fullPaths) > $max) {
            $toDelete = \array_slice($fullPaths, 0, \count($fullPaths) - $max);
            foreach ($toDelete as $dir) {
                $this->recursiveDelete($dir);
            }
        }
    }

    /**
     * Hilfsfunktion zum Löschen eines Ordners inkl. Inhalt
     */
    private function recursiveDelete(string $dir): void
    {
        if (! \is_dir($dir)) {
            return;
        }
        $files = \array_diff(\scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (\is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : \unlink("$dir/$file");
        }
        \rmdir($dir);
    }

    /**
     * Erstellt die Tabellen basierend auf der config/schema.php
     */
    public function ensureTablesExist(): void
    {
        if (! $this->pdo) {
            return;
        }

        $schema = $this->config->get('db_schema', []);
        foreach ($schema as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (\PDOException $e) {
                // Fehler silent loggen oder behandeln, falls nötig
            }
        }
    }

    /**
     * Füllt die Datenbank/JSON-Dateien beim Erststart mit Standardwerten
     * ODER übernimmt Daten aus der jeweils anderen Quelle.
     */
    public function seedInitialData(): void
    {
        $this->seedGroups();
        $this->seedUsers();
    }

    private function seedGroups(): void
    {
        $cfg         = $this->config->get('storage_config')['groups'];
        $currentType = $cfg['type']; // 'json' oder 'mysql'

        // 1. Daten aus beiden Welten "roh" laden
        $jsonData = $this->loadRawJson('groups');
        $sqlData  = $this->loadRawSql('groups');

        // 2. Prüfen, ob die AKTIVE Welt leer ist
        $activeData = ($currentType === 'json') ? $jsonData : $sqlData;

        if (! empty($activeData)) {
            return; // Nichts tun, wir haben schon Daten
        }

        // 3. Logik: Wenn aktiv leer, schaue ob die andere Welt Daten hat
        $sourceData = [];
        if ($currentType === 'mysql' && ! empty($jsonData)) {
            $sourceData = $jsonData; // Von JSON zu SQL umgezogen
        } elseif ($currentType === 'json' && ! empty($sqlData)) {
            $sourceData = $sqlData; // Von SQL zu JSON umgezogen
        } else {
            // 4. Beide Welten leer? Dann modernisierte Standard-Impfung mit neuen UUIDs
            $sourceData = [
                'grp_71cb1c0d' => [
                    'name'        => 'Administrator',
                    'permissions' => ['*'],
                ],
                'grp_180a3ec6' => [
                    'name'        => 'Finanzen',
                    'permissions' => [
                        'privacy.finance.reveal',
                        'privacy.email.reveal',
                        'check.admin.print',
                        'dashboard.view',
                        'dashboard.control_bar.view',
                        'dashboard.control_bar.future',
                        'dashboard.control_bar.search',
                        'dashboard.info_alert.view',
                        'dashboard.info_alert.print',
                        'dashboard.info_alert.details',
                        'dashboard.active.view',
                        'dashboard.active.print',
                        'dashboard.active.details',
                        'dashboard.active.suspend',
                        'dashboard.finance.view',
                        'dashboard.finance.details',
                        'dashboard.finance.mark_paid',
                        'dashboard.future.view',
                        'dashboard.future.print',
                        'dashboard.future.details',
                        'dashboard.expired.view',
                        'dashboard.expired.print',
                        'dashboard.expired.details',
                        'dashboard.stats.view',
                        'dashboard.stats.current',
                        'dashboard.stats.charts',
                        'dashboard.stats.history',
                        'dashboard.ranking.view',
                        'dashboard.export.view',
                        'finance.export.execute',
                        'dashboard.export.csv',
                        'dashboard.export.json',
                        'dashboard.vouchers.view',
                        'dashboard.vouchers.open',
                        'dashboard.vouchers.suspend',
                        'dashboard.vouchers.remove',
                        'dashboard.vouchers.archive',
                        'dashboard.tools.view',
                        'dashboard.tools.direct_issue.reveal',
                        'dashboard.tools.direct_issue.execute',
                        'dashboard.tools.voucher_gen.reveal',
                        'dashboard.tools.voucher_gen.execute',
                        'template.manage',
                        'template.std.7',
                        'template.std.14',
                        'template.std.30',
                        'template.perm.3',
                        'template.perm.6',
                        'template.perm.9',
                        'template.perm.12',
                        'template.custom.std',
                        'template.custom.perm',
                    ],
                ],
                'grp_fd72d38c' => [
                    'name'        => 'Sachbearbeitung',
                    'permissions' => [
                        'privacy.email.reveal',
                        'check.admin.print',
                        'dashboard.view',
                        'dashboard.control_bar.view',
                        'dashboard.control_bar.future',
                        'dashboard.control_bar.search',
                        'dashboard.info_alert.view',
                        'dashboard.info_alert.print',
                        'dashboard.info_alert.details',
                        'dashboard.active.view',
                        'dashboard.active.print',
                        'dashboard.active.details',
                        'dashboard.finance.view',
                        'dashboard.finance.details',
                        'dashboard.future.view',
                        'dashboard.future.print',
                        'dashboard.future.details',
                        'dashboard.expired.view',
                        'dashboard.vouchers.view',
                        'dashboard.vouchers.open',
                        'dashboard.vouchers.suspend',
                        'dashboard.tools.view',
                        'dashboard.tools.direct_issue.reveal',
                        'dashboard.tools.direct_issue.execute',
                        'dashboard.tools.voucher_gen.reveal',
                        'dashboard.tools.voucher_gen.execute',
                        'dashboard.logs.view',
                        'template.manage',
                        'template.std.7',
                        'template.std.14',
                        'template.std.30',
                        'template.perm.3',
                        'template.perm.6',
                        'template.perm.9',
                        'template.perm.12',
                        'template.custom.std',
                        'template.custom.perm',
                    ],
                ],
                'grp_a53d6b56' => [
                    'name'        => 'Prüfer vor Ort',
                    'permissions' => [
                        'dashboard.view',
                        'dashboard.control_bar.view',
                        'dashboard.control_bar.future',
                        'dashboard.control_bar.search',
                        'dashboard.active.view',
                        'dashboard.active.details',
                    ],
                ],
            ];
        }

        // 5. In die aktive Welt schreiben
        if ($currentType === 'mysql') {
            foreach ($sourceData as $id => $data) {
                $stmt = $this->pdo->prepare('REPLACE INTO `groups` (id, name, permissions) VALUES (?, ?, ?)');
                $stmt->execute([$id, $data['name'], \json_encode($data['permissions'])]);
            }
        } else {
            $this->saveToJson('groups', $sourceData);
        }
    }

    private function seedUsers(): void
    {
        $cfg         = $this->config->get('storage_config')['users'];
        $currentType = $cfg['type'];

        $jsonData = $this->loadRawJson('users');
        $sqlData  = $this->loadRawSql('users');

        $activeData = ($currentType === 'json') ? $jsonData : $sqlData;
        if (! empty($activeData)) {
            return;
        }

        $sourceData = [];
        if ($currentType === 'mysql' && ! empty($jsonData)) {
            $sourceData = $jsonData;
        } elseif ($currentType === 'json' && ! empty($sqlData)) {
            $sourceData = $sqlData;
        } else {
            // Erststart-Impfung mit neuem ID-Schema und nativem, vorberechnetem Hash
            $sourceData = [
                'usr_7c13b491' => [
                    'username' => 'Admin',
                    'group'    => 'grp_71cb1c0d',
                    'pass'     => '$2y$12$DHelEqSuvcbbGPYWqnIrIOfs/PYaMVfyahWHkW.aRM43syMd5ASoW',
                ],
            ];
        }

        // Speichern
        if ($currentType === 'mysql') {
            $this->authService->saveUsers($sourceData);
        } else {
            $this->saveToJson('users', $sourceData);
        }
    }
}
