<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Storage\JsonStorage;
use App\Infrastructure\Storage\MySqlStorage;

/**
 * Service für Daten-Migrationen, automatisierte Datensicherungen (Backups) und System-Recovery.
 *
 * Überträgt relationale und flache Dateistrukturen (JSON <-> MySQL) bidirektional,
 * steuert automatische Backup-Zyklen und stellt Tabellen-Schemata sowie Initialdaten (Seeding) her.
 * Kontext: Administrativer Wartungs- und Backup-Manager der Anwendung.
 *
 * Path: src/Core/Service/MigrationService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
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

    /**
     * Orchestriert eine Migrationsaktion für einen Datenbereich.
     * Erstellt vorab zwingend ein Sicherheitsbackup und verzweigt dann in die Sub-Migrationsschritte.
     *
     * @param string $target Der Schlüssel des Speicherbereichs (z.B. 'permits', 'users', 'vouchers').
     * @param string $action Die Migrationsart ('json_to_mysql', 'mysql_to_json', 'sync').
     *
     * @return string HTML-formatierte Erfolgs- oder Fehlermeldung für das Admin-Interface.
     */
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
     * Generiert ein synchronisiertes Datei- und Datenbank-Abbild eines Zielbereichs im Backup-Ordner.
     * Erzeugt Zeitstempel-Ordner und exportiert JSON-formatierte Rohdaten-Dumps.
     *
     * @param string $target Der zu sichernde Konfigurationsbereich.
     *
     * @return string Relativer Pfad zum erstellten Backup-Ordner.
     */
    private function createAutoBackup(string $target): string
    {
        $timestamp  = \date('Ymd-His'); // YYYYMMDD-HHmmss
        $root       = $this->config->get('root_path');
        $prefix     = $this->config->get('storage_path_prefix');
        $subFolder  = $this->config->get('backup_settings')['sub_folder'] ?? 'backup'; // Nutzt Namen aus Config
        $backupPath = \rtrim($root, '/\\') . '/' . \ltrim($prefix, '/\\') . $subFolder . '/' . $timestamp;

        if (! \is_dir($backupPath)) {
            \mkdir($backupPath, 0o777, true);
        }

        // Flags: 128 (PRETTY) + 64 (UNESCAPED_SLASHES) + 256 (UNESCAPED_UNICODE) = 448
        $jsonFlags     = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        $storageConfig = $this->config->get('storage_config', []);

        // FIX: Wenn das Target ein virtueller Key wie 'auto_maintenance' ist, sichern wir die Kern-Komponenten pauschal
        if (! isset($storageConfig[$target])) {
            $keysToBackup = ['permits', 'users', 'groups', 'vouchers'];
            foreach ($keysToBackup as $key) {
                if (! isset($storageConfig[$key])) {
                    continue;
                }

                $path = \rtrim((string) $root, '/\\') . '/' .
                    \ltrim((string) $prefix, '/\\') . $storageConfig[$key]['file'];
                if (! \file_exists($path)) {
                    continue;
                }

                $data = \json_decode((string) \file_get_contents($path), true) ?? [];
                \file_put_contents($backupPath . "/{$key}_file.json", \json_encode($data, $jsonFlags));
            }

            return $prefix . $subFolder . '/' . $timestamp;
        }

        // Standard-Logik für existierende, spezifische Keys
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

    /**
     * Exportiert Tabellendaten aus MySQL in eine flache JSON-Datei.
     * Nutzt bei Permits das optimierte StorageInterface::migrateTo().
     *
     * @param string $target Der zu exportierende Bereich.
     *
     * @return string Statusmeldung über die Anzahl exportierter Datensätze.
     */
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

    /**
     * Importiert flache JSON-Datensätze in die relationale MySQL-Struktur.
     *
     * @param string $target Der zu importierende Bereich.
     *
     * @return string Statusmeldung über die importierten Zeilen.
     */
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

    /**
     * Führt JSON- und MySQL-Bestände über ein rekursives Array-Merging zusammen und gleicht beide Backends an.
     *
     * @param string $target Der zu konsolidierende Datenbereich.
     *
     * @return string Erfolgsmeldung inklusive Gesamtanzahl der Datensätze.
     */
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

    /**
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

        // FIX: Prüfen, ob überhaupt ein Dateiname konfiguriert ist
        if (! isset($cfg['file'])) {
            return [];
        }

        $path = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * Schreibt Daten-Arrays formatiert zurück in die physische JSON-Zieldatei.
     * Robust gegen fehlende 'file'-Keys.
     *
     * @param string               $key  Speicher-Key.
     * @param array<string, mixed> $data Die zu serialisierenden Daten.
     */
    private function saveToJson(string $key, array $data): void
    {
        $cfg = $this->config->get('storage_config')[$key];

        // FIX: Nur speichern, wenn ein Dateiname definiert ist
        if (! isset($cfg['file'])) {
            return;
        }

        $path = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];
        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
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
        if (! $this->pdo) {
            return [];
        }

        try {
            // Wir loggen kurz den Tabellennamen zur Sicherheit
            $tableName = $cfg['table'];
            $stmt      = $this->pdo->query("SELECT * FROM `$tableName`");
            $rows      = $stmt->fetchAll();

            if (empty($rows)) {
                \error_log("Bootstrap: MySQL-Tabelle `$tableName` ist leer.");

                return [];
            }
        } catch (\PDOException $e) {
            \error_log("Bootstrap: MySQL-Query Fehler bei Tabelle `$tableName`: " . $e->getMessage());

            return [];
        }

        $res = [];
        // Dynamische Bestimmung des ID-Feldes
        $idField = match ($key) {
            'users', 'groups', 'mail_log', 'mail_queue', 'vouchers_archive' => 'id',
            'magic_links', 'pending_verification', 'verified_pending'       => 'token',
            default                                                         => 'code'
        };

        foreach ($rows as $r) {
            // ROBUSTHEIT: Überspringe Zeilen ohne den erwarteten Primärschlüssel
            if (! isset($r[$idField])) {
                continue;
            }

            if (isset($r['data'])) {
                $r['data'] = \json_decode((string) $r['data'], true);
            }
            $res[$r[$idField]] = $r;
        }

        return $res;
    }

    /**
     * Routet Rohdaten an die zuständigen Service-Klassen zur korrekten SQL-Persistierung weiter.
     * Nutzt einen generischen Fallback für Tabellen ohne spezifischen Service.
     *
     * @param string               $key  Ziel-Domainkomponente.
     * @param array<string, mixed> $data Liste der zu injectenden Datensätze.
     */
    private function saveToSql(string $key, array $data): void
    {
        // Wir delegieren an die Services, da diese bereits die Logik für "Save" haben!
        // Das ist sauberer als genericSqlInsert, da die Services die Spalten kennen.
        match ($key) {
            'users'                => $this->authService->saveUsers($data),
            'groups'               => $this->authService->saveGroups($data),
            'vouchers'             => $this->voucherService->saveVouchers($data),
            'magic_links'          => $this->magicLinkService->saveLinks($data),
            'mail_log'             => $this->mailService->saveLogs($data),
            'pending_verification' => $this->permitService->savePendingData('pending_verification', $data),
            'verified_pending'     => $this->permitService->savePendingData('verified_pending', $data),
            'permits'              => $this->migratePermitsToSql($data),
            default                => $this->genericSqlInsert($key, $data)
        };
    }

    /**
     * Generischer SQL-Importer für beliebige Tabellen.
     * Erzeugt dynamisch ein REPLACE INTO Statement anhand der Keys des zu importierenden Arrays.
     *
     * @param string $key  Speicher-Key aus der Config.
     * @param array  $data Die zu importierenden Rohdaten.
     */
    private function genericSqlInsert(string $key, array $data): void
    {
        if (empty($data) || ! $this->pdo) {
            return;
        }

        $cfg       = $this->config->get('storage_config')[$key];
        $tableName = $cfg['table'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `$tableName`");

            foreach ($data as $id => $row) {
                // Sicherstellen, dass der Key in der Row ist (falls JSON assoziativ)
                if (! isset($row['id']) && ! isset($row['code']) && ! isset($row['token'])) {
                    $row['id'] = $id;
                }

                // Mapping: Falls alte Keys vorliegen, anpassen (z.B. templateKey -> template_key)
                if (isset($row['templateKey'])) {
                    $row['template_key'] = $row['templateKey'];
                    unset($row['templateKey']);
                }

                $columns      = \array_keys($row);
                $colNames     = \implode(', ', \array_map(fn ($c) => "`$c`", $columns));
                $placeholders = \implode(', ', \array_fill(0, \count($columns), '?'));
                $stmt         = $this->pdo->prepare("REPLACE INTO `$tableName` ($colNames) VALUES ($placeholders)");

                $values = [];
                foreach ($columns as $col) {
                    $val = $row[$col];
                    // Arrays (z.B. bei 'data' Feld) müssen JSON-String sein
                    $values[] = \is_array($val) ? \json_encode($val, \JSON_UNESCAPED_UNICODE) : $val;
                }
                $stmt->execute($values);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            \error_log("Generic Migration Error for $key: " . $e->getMessage());
        }
    }

    // Kleine Hilfsmethoden, um saveToSql sauber zu halten:

    /**
     * Hilfsmethode zur spezifischen SQL-Hydrierung und Speicherung von Genehmigungs-Entitäten.
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function migratePermitsToSql(array $data): void
    {
        if (! $this->pdo) {
            return;
        }
        $storage = new MySqlStorage($this->pdo);

        foreach ($data as $key => $item) {
            // Falls das Array assoziativ ist (Key=Code), nutze $item
            // Wir müssen sicherstellen, dass 'code' im Item gesetzt ist
            if (! isset($item['code'])) {
                $item['code'] = $key;
            }
            $storage->save($this->permitService->arrayToEntity($item));
        }
    }

    /**
     * Löst den vollen Absolut-Pfad einer JSON-Speicherdatei auf.
     *
     * @param string $key Speicherbereich.
     *
     * @return string Physischer Dateipfad.
     */
    private function getFilePath(string $key): string
    {
        $cfg = $this->config->get('storage_config')[$key];

        return $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
    }

    /**
     * Scannt das Backup-Verzeichnis und listet alle verfügbaren Backup-Stände und deren Dateiinhalte auf.
     * Listet alle verfügbaren Backup-Ordner sortiert nach Datum (neuere zuerst).
     *
     * @return array<string, array<int, string>> Absteigend sortiertes Array (neueste zuerst) von Datei-Listen.
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
            if (! \is_dir($fullPath)) {
                continue;
            }

            // Prüfen, welche Dateien im Backup liegen
            $files           = \array_diff(\scandir($fullPath), ['.', '..']);
            $result[$folder] = \array_values($files);
        }

        \krsort($result); // Neueste Backups oben

        return $result;
    }

    /**
     * Stellt einen spezifischen Datenstand aus einem Backup-Ordner wieder her.
     * Sichert den aktuellen Ist-Zustand vorab unter dem Präfix `_before_restore` ab.
     *
     * @param string $timestamp Der Ordnername (Zeitstempel) des Quell-Backups.
     * @param string $target    Der Zielbereich, welcher überschrieben werden soll.
     *
     * @return string Status-Ergebnistext für das Admin-Frontend.
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
     * Überwacht und steuert automatisierte Backup-Intervalle im Hintergrund.
     * Prüft anhand eines Timestamps in `last_auto_backup.txt`, ob ein konfiguriertes Intervall abgelaufen ist,
     * stößt die Sicherung an und rotiert alte Backups (Retention Rate) aus.
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

        if (\time() - $lastBackup <= $interval) {
            return;
        }

        // 1. Neues Backup erstellen
        $this->createAutoBackup('auto_maintenance');
        \file_put_contents($stateFile, (string) \time());

        // 2. Alte Backups löschen (Rotation)
        $this->rotateBackups((int) ($cfg['max_backups'] ?? 10));
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
        $backupPath = $root . $prefix . 'backup';

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

        \ksort($fullPaths); // Sortiert chronologisch (älteste zuerst)

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
     * Erstellt die physischen MySQL-Tabellenstrukturen zur Laufzeit, falls diese nicht existieren.
     * Nutzt das in der Konfiguration geladene SQL-Schema.
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
                // Jetzt wird `$tableName` sinnvoll genutzt!
                \error_log("Bootstrap: Fehler beim Erstellen der Tabelle '$tableName': " . $e->getMessage());
            }
        }
    }

    /**
     * Initiiert das Seeding und automatische Migrieren von Grunddaten für ALLE konfigurierten Speicherbereiche.
     * Wenn 'auto_migration' in der Config auf false steht, wird dieser Prozess (bis auf ensureTablesExist)
     * übersprungen.
     *
     * Füllt die Datenbank/JSON-Dateien beim Erststart mit Standardwerten
     * ODER übernimmt Daten automatisch bidirektional aus der jeweils anderen (inaktiven) Quelle.
     *
     * TODO @param und @return einfügen
     */
    public function seedInitialData(): void
    {
        // Ressourcen-Schoner: Nur ausführen, wenn in der Config aktiviert
        if (! $this->config->get('auto_migration', true)) {
            return;
        }

        $storageKeys = [
            'groups',
            'users',
            'vouchers',
            'vouchers_archive',
            'permits',
            'permits_archive',
            'mail_log',
            'mail_queue',
            'magic_links',
            'pending_verification',
            'verified_pending',
        ];

        foreach ($storageKeys as $key) {
            $this->autoBootstrapStorage($key);
        }
    }

    /**
     * Universelle Bootstrap-Logik für jeden einzelnen Speicherbereich.
     * Erkennt leere aktive Speicher und füllt diese mit Daten aus dem passiven Speicher oder mit Defaults.
     * TODO @param und @return einfügen
     */
    private function autoBootstrapStorage(string $key): void
    {
        $cfg = $this->config->get('storage_config')[$key] ?? null;
        if (! $cfg) {
            return;
        }

        $currentType = $cfg['type'] ?? 'json';

        // 1. Daten laden
        $jsonData = $this->loadRawJson($key);
        $sqlData  = $this->loadRawSql($key);

        // 2. Ziel-Check: Ist der AKTIVE Speicher leer?
        $activeData = $currentType === 'mysql' ? $sqlData : $jsonData;

        // 3. Ist der aktive Speicher befüllt? -> Nichts tun!
        if (! empty($activeData)) {
            return; // Ziel voll -> Alles okay
        }

        // 3. Quell-Check: Gibt es im anderen Speicher Daten?
        // Wenn currentType mysql, dann ist JSON (jsonData) unsere Quelle
        $sourceData = $currentType === 'mysql' ? $jsonData : $sqlData;

        // DEBUG-Logging
        if (empty($sourceData)) {
            // Defaults nur für User/Groups
            if ($key === 'groups') {
                $sourceData = $this->getDefaultGroups();
                \error_log("Bootstrap: Schreibe Default-Daten für $key.");
            } elseif ($key === 'users') {
                $sourceData = $this->getDefaultUsers();
                \error_log("Bootstrap: Schreibe Default-Daten für $key.");
            } else {
                \error_log("Bootstrap: Keine Daten für $key gefunden (weder JSON noch SQL noch Default). Überspringe.");

                return;
            }
        } else {
            \error_log(
                "Bootstrap: Migriere $key von " .
                    ($currentType === 'mysql' ? 'JSON' : 'SQL') . ' zu ' .
                    ($currentType === 'mysql' ? 'SQL' : 'JSON'),
            );
        }

        // 4. Echte Migration
        try {
            if ($currentType === 'mysql') {
                $this->saveToSql($key, $sourceData);
            } else {
                $this->saveToJson($key, $sourceData);
            }
            \error_log("Bootstrap: $key erfolgreich migriert/befüllt.");
        } catch (\Exception $e) {
            \error_log("Bootstrap: FEHLER bei Migration von $key: " . $e->getMessage());
        }
    }

    /**
     * Erstellt Standard-Rollen (Admin, Finanzen, Sachbearbeitung, Prüfer) inklusive fein-granularer Rechte.
     */
    private function seedGroups(): void
    {
        $cfg         = $this->config->get('storage_config')['groups'];
        $currentType = $cfg['type']; // 'json' oder 'mysql'

        // 1. Daten aus beiden Welten "roh" laden
        $jsonData = $this->loadRawJson('groups');
        $sqlData  = $this->loadRawSql('groups');

        // 2. Prüfen, ob die AKTIVE Welt leer ist
        $activeData = $currentType === 'json' ? $jsonData : $sqlData;

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
            // Ausgelagerte Methode für Standard-Daten nutzen
            $sourceData = $this->getDefaultGroups();
        }

        if ($currentType === 'mysql') {
            foreach ($sourceData as $id => $data) {
                $stmt = $this->pdo->prepare('REPLACE INTO `groups` (id, name, permissions) VALUES (?, ?, ?)');
                $stmt->execute([$id, $data['name'], \json_encode($data['permissions'])]);
            }
        } else {
            $this->saveToJson('groups', $sourceData);
        }
    }

    /**
     * Erstellt den initialen System-Admin-Benutzer accountseitig, falls die Tabellen/Dateien leer sind.
     */
    private function seedUsers(): void
    {
        $cfg         = $this->config->get('storage_config')['users'];
        $currentType = $cfg['type'];

        $jsonData = $this->loadRawJson('users');
        $sqlData  = $this->loadRawSql('users');

        $activeData = $currentType === 'json' ? $jsonData : $sqlData;
        if (! empty($activeData)) {
            return;
        }

        $sourceData = [];
        if ($currentType === 'mysql' && ! empty($jsonData)) {
            $sourceData = $jsonData;
        } elseif ($currentType === 'json' && ! empty($sqlData)) {
            $sourceData = $sqlData;
        } else {
            // Ausgelagerte Methode für Standard-Daten nutzen
            $sourceData = $this->getDefaultUsers();
        }

        if ($currentType === 'mysql') {
            $this->authService->saveUsers($sourceData);
        } else {
            $this->saveToJson('users', $sourceData);
        }
    }

    /**
     * Liefert das Standard-Rechtesetup für einen frischen Systemstart.
     * Ausgelagert für bessere Lesbarkeit und Code-Wiederverwendung.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultGroups(): array
    {
        return [
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
                    'dashboard.finance.suspend',
                    'dashboard.finance.mark_paid',
                    'dashboard.future.view',
                    'dashboard.future.print',
                    'dashboard.future.details',
                    'dashboard.future.suspend',
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

    /**
     * Liefert das Standard-Nutzer-Setup (Initiale Admin-Logins) für einen Systemstart.
     *
     * @return array<string, array<string, string>>
     */
    private function getDefaultUsers(): array
    {
        return [
            'usr_7c13b491' => [
                'username' => 'Admin',
                'group'    => 'grp_71cb1c0d',
                'pass'     => '$2y$12$DHelEqSuvcbbGPYWqnIrIOfs/PYaMVfyahWHkW.aRM43syMd5ASoW',
            ],
        ];
    }
}
