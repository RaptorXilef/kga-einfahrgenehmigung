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
        private ?\PDO $pdo,
        private AuthService $authService,
        private BackupService $backupService,
        private ConfigInterface $config,
        private MagicLinkService $magicLinkService,
        private MailServiceInterface $mailService,
        private PermitService $permitService,
        private VoucherService $voucherService,
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
            $backupFolder = $this->backupService->createBackup($target);
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
        if ($data === []) {
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
        if ($data === []) {
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
        // Bei Permits nutzen wir die Domain-Objekte für den sauberen Sync
        if ($target === 'permits') {
            $json = new JsonStorage($this->getFilePath('permits'));
            $sql  = new MySqlStorage($this->pdo);

            $jsonPermits = $json->getAll();
            $sqlPermits  = $sql->getAll();

            $count = 0;
            // SQL nach JSON syncen
            foreach ($sqlPermits as $p) {
                $json->save($p);
                ++$count;
            }
            // JSON nach SQL syncen
            foreach ($jsonPermits as $p) {
                $sql->save($p);
                ++$count;
            }

            return "Erfolg: '$target' (Domain-Entitäten) synchronisiert.";
        }

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
     * Schreibt Daten-Arrays formatiert zurück in die physische JSON-Zieldatei.
     * Robust gegen fehlende 'file'-Keys.
     *
     * @param string               $key  Speicher-Key.
     * @param array<string, mixed> $data Die zu serialisierenden Daten.
     */
    private function saveToJson(string $key, array $data): void
    {
        $cfg = $this->config->get('storage_config')[$key];

        // Nur speichern, wenn ein Dateiname definiert ist
        if (! isset($cfg['file'])) {
            return;
        }

        // Normalisierung vor dem Schreiben ins JSON: Wenn Daten aus SQL kommen, sind 'data' oder 'permissions' Objekte eventuell noch Arrays.
        // Das sorgt für eine saubere, einheitliche Struktur im Dateisystem.
        $jsonFlags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        \file_put_contents($this->getFilePath($key), \json_encode($data, $jsonFlags));
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
        // Wir nutzen die Services als stark typisierte Bausteine statt einer unsicheren generic-Schleife
        // Wir delegieren an die Services, da diese bereits die Logik für "Save" haben!
        // Das ist sauberer als genericSqlInsert, da die Services die Spalten kennen.
        match ($key) {
            'users'                => $this->authService->saveUsers($data, true),
            'groups'               => $this->authService->saveGroups($data, true),
            'vouchers'             => $this->voucherService->saveVouchers($data, true),
            'magic_links'          => $this->magicLinkService->saveLinks($data, true),
            'mail_log'             => $this->mailService->saveLogs($data, true),
            'pending_verification' => $this->permitService->savePendingData('pending_verification', $data, true),
            'verified_pending'     => $this->permitService->savePendingData('verified_pending', $data, true),
            'permits'              => $this->migratePermitsToSql($data),
            default                => throw new \InvalidArgumentException("Kein SQL-Mapper für Speicherbereich '$key' definiert.")
        };
    }

    // Kleine Hilfsmethoden, um saveToSql sauber zu halten:

    /**
     * Hilfsmethode zur spezifischen SQL-Hydrierung und Speicherung von Genehmigungs-Entitäten.
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function migratePermitsToSql(array $data): void
    {
        if (! $this->pdo instanceof \PDO) {
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

    // TODO DocBlock erstellen
    private function getIdFieldForKey(string $key): string
    {
        return match ($key) {
            'users', 'groups', 'mail_log', 'mail_queue', 'vouchers_archive' => 'id',
            'magic_links', 'pending_verification', 'verified_pending'       => 'token',
            default                                                         => 'code'
        };
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

        return \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
               \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
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
        // 1. Sicherheitshalber aktuellen Ist-Zustand sichern
        $this->backupService->createBackup($target . '_before_restore');

        // 2. Daten aus Backup abrufen
        $data = $this->backupService->getBackupData($timestamp, $target);

        if ($data === null) {
            return "Fehler: Keine gültige Backup-Datei für '$target' im Ordner $timestamp gefunden.";
        }

        // 3. Ins Zielsystem schreiben (egal ob JSON oder SQL)
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
     *TODO Prüfen ob diese noch benötigt wird oder gelöschtw erden kann!
     * Erstellt die physischen MySQL-Tabellenstrukturen zur Laufzeit, falls diese nicht existieren.
     * Nutzt das in der Konfiguration geladene SQL-Schema.
     */
    public function ensureTablesExist(): void
    {
        if (! $this->pdo instanceof \PDO) {
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
     * TODO Später löschen
     * Erstellt Standard-Rollen (Admin, Finanzen, Sachbearbeitung, Prüfer) inklusive fein-granularer Rechte.
     *
     * private function seedGroups(): void
     * {
     * $cfg         = $this->config->get('storage_config')['groups'];
     * $currentType = $cfg['type']; // 'json' oder 'mysql'
     *
     * // 1. Daten aus beiden Welten "roh" laden
     * $jsonData = $this->loadRawJson('groups');
     * $sqlData  = $this->loadRawSql('groups');
     *
     * // 2. Prüfen, ob die AKTIVE Welt leer ist
     * $activeData = $currentType === 'json' ? $jsonData : $sqlData;
     *
     * if (! empty($activeData)) {
     * return; // Nichts tun, wir haben schon Daten
     * }
     *
     * // 3. Logik: Wenn aktiv leer, schaue ob die andere Welt Daten hat
     * $sourceData = [];
     * if ($currentType === 'mysql' && ! empty($jsonData)) {
     * $sourceData = $jsonData; // Von JSON zu SQL umgezogen
     * } elseif ($currentType === 'json' && ! empty($sqlData)) {
     * $sourceData = $sqlData; // Von SQL zu JSON umgezogen
     * } else {
     * // Ausgelagerte Methode für Standard-Daten nutzen
     * $sourceData = $this->getDefaultGroups();
     * }
     *
     * if ($currentType === 'mysql') {
     * foreach ($sourceData as $id => $data) {
     * $stmt = $this->pdo->prepare('REPLACE INTO `groups` (id, name, permissions) VALUES (?, ?, ?)');
     * $stmt->execute([$id, $data['name'], \json_encode($data['permissions'])]);
     * }
     * } else {
     * $this->saveToJson('groups', $sourceData);
     * }
     * }*/

    /**
     * TODO Später löschen
     * Erstellt den initialen System-Admin-Benutzer accountseitig, falls die Tabellen/Dateien leer sind.
     *
     * private function seedUsers(): void
     * {
     * $cfg         = $this->config->get('storage_config')['users'];
     * $currentType = $cfg['type'];
     *
     * $jsonData = $this->loadRawJson('users');
     * $sqlData  = $this->loadRawSql('users');
     *
     * $activeData = $currentType === 'json' ? $jsonData : $sqlData;
     * if (! empty($activeData)) {
     * return;
     * }
     *
     * $sourceData = [];
     * if ($currentType === 'mysql' && ! empty($jsonData)) {
     * $sourceData = $jsonData;
     * } elseif ($currentType === 'json' && ! empty($sqlData)) {
     * $sourceData = $sqlData;
     * } else {
     * // Ausgelagerte Methode für Standard-Daten nutzen
     * $sourceData = $this->getDefaultUsers();
     * }
     *
     * if ($currentType === 'mysql') {
     * $this->authService->saveUsers($sourceData);
     * } else {
     * $this->saveToJson('users', $sourceData);
     * }
     * }*/

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
        $path = $this->getFilePath($key);

        return \file_exists($path) ? (\json_decode(
            (string) \file_get_contents($path),
            true,
        )
            ?? []) : [];
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
            // Wir loggen kurz den Tabellennamen zur Sicherheit
            $tableName = $cfg['table'];
            $stmt      = $this->pdo->query("SELECT * FROM `$tableName`");
            $rows      = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                \error_log("Bootstrap: MySQL-Tabelle `$tableName` ist leer.");

                return [];
            }
        } catch (\PDOException $e) {
            \error_log("Migration SQL-Load Fehler ($key): " . $e->getMessage());

            return [];
        }

        $res     = [];
        $idField = $this->getIdFieldForKey($key);

        foreach ($rows as $r) {
            // WICHTIG: Hier entpacken wir MySQL-JSON-Strings in echte PHP-Arrays,
            // damit file_put_contents später sauberes, verschachteltes JSON schreibt
            // und keine hässlichen "{\"name\":\"Test\"}" Strings.

            if (isset($r['data']) && \is_string($r['data'])) {
                $decoded   = \json_decode($r['data'], true);
                $r['data'] = $decoded !== null ? $decoded : [];
            }

            if (isset($r['permissions']) && \is_string($r['permissions'])) {
                $decoded          = \json_decode($r['permissions'], true);
                $r['permissions'] = $decoded !== null ? $decoded : [];
            }

            // Sicherstellen, dass Zahlen auch als Zahlen im JSON landen (optional, aber sauber)
            if (isset($r['value'])) {
                $r['value'] = (float) $r['value'];
            }
            if (isset($r['uses_count'])) {
                $r['uses_count'] = (int) $r['uses_count'];
            }
            if (isset($r['max_uses'])) {
                $r['max_uses'] = (int) $r['max_uses'];
            }
            if (isset($r['multi_use'])) {
                $r['multi_use'] = (bool) $r['multi_use'];
            }
            if (isset($r['is_suspended'])) {
                $r['is_suspended'] = (int) $r['is_suspended'];
            }

            $res[$r[$idField]] = $r;
        }

        return $res;
    }

    // TODO DocBlock erstellen
    public function clearCache(): string
    {
        $root = $this->config->get('root_path');

        // 1. Deptrac Cache löschen
        $deptracCache = $root . '/.cache/deptrac/.deptrac.cache';
        if (\file_exists($deptracCache)) {
            \unlink($deptracCache);
        }

        // 2. Session-Rechte neu kompilieren (für den aktuellen Admin)
        $this->authService->refreshSessionPermissions($this->authService->getGroup());

        return 'Erfolg: Der System-Cache wurde geleert und die Berechtigungen neu kompiliert.';
    }

    // TODO DocBlock erstellen
    public function truncateTarget(string $target): string
    {
        // 1. Zwingendes Backup vor der Löschung!
        try {
            $this->backupService->createBackup($target . '_before_truncate');
        } catch (\Exception $e) {
            return 'Abbruch: Sicherheits-Backup konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        // 2. SQL oder JSON leeren
        $cfg = $this->config->get('storage_config')[$target] ?? null;
        if (! $cfg) {
            return "Fehler: Unbekannter Speicherbereich '$target'.";
        }

        $clearedIn = [];

        // MySQL Tabelle leeren
        if ($this->pdo instanceof \PDO) {
            try {
                $tableName = $cfg['table'];
                $this->pdo->exec("TRUNCATE TABLE `$tableName`");
                $clearedIn[] = 'MySQL';
            } catch (\PDOException $e) {
                \error_log('Truncate Error MySQL: ' . $e->getMessage());
            }
        }

        // JSON Datei leeren (mit einem leeren Array überschreiben)
        $path = $this->getFilePath($target);
        if (\file_exists($path)) {
            $jsonFlags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE;
            \file_put_contents($path, \json_encode([], $jsonFlags));
            $clearedIn[] = 'JSON';
        }

        if (empty($clearedIn)) {
            return 'Hinweis: Es wurden keine Daten gefunden, die gelöscht werden konnten.';
        }

        return "Erfolg: Der Bereich '$target' wurde vollständig geleert (" . \implode(' & ', $clearedIn) . '). Ein Backup wurde erstellt.';
    }
}
