<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Storage\JsonHelper;
use App\Infrastructure\Storage\JsonStorage;
use App\Infrastructure\Storage\MySqlStorage;
use App\Infrastructure\Storage\SafeJsonWriterTrait;

/**
 * Service für Daten-Migrationen, automatisierte Datensicherungen (Backups) und System-Recovery.
 *
 * Überträgt relationale und flache Dateistrukturen (JSON <-> MySQL) bidirektional,
 * steuert automatische Backup-Zyklen und stellt Tabellen-Schemata sowie Initialdaten (Seeding) her.
 * Kontext: Administrativer Wartungs- und Backup-Manager der Anwendung.
 *
 * Path: src/Infrastructure/Maintenance/MigrationService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MigrationService
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ?\PDO $pdo,
        private AuthService $authService,
        private BackupService $backupService,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private MagicLinkRepositoryInterface $magicLinkRepository,
        private MailLogInterface $mailLog,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private StorageInterface $storage,
        private UserRepositoryInterface $userRepository,
        private VerificationRepositoryInterface $verificationRepository,
        private VoucherRepositoryInterface $voucherRepository,
    ) {
    }

    // --- Public Dashboard Actions ---

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
        // [x] Sortiert
        $result = match ($action) {
            'json_to_mysql' => $this->migrateJsonToSql($target),
            'mysql_to_json' => $this->migrateSqlToJson($target),
            'sync'          => $this->syncBoth($target),
            default         => 'Fehler: Unbekannte Aktion.'
        };

        return "Backup erstellt in $backupFolder. <br>" . $result;
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
    public function restore(string $timestamp, string $target, string $engine = 'all'): string
    {
        // 1. Zwingendes Sicherheitsbackup vor der Wiederherstellung
        try {
            $this->backupService->createBackup($target . '_before_restore');
        } catch (\Exception $e) {
            return 'Abbruch: Sicherheits-Backup des Ist-Zustands konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        // 2. Daten aus dem Backup-Archiv abrufen
        $data = $this->backupService->getBackupData($timestamp, $target);
        if ($data === null) {
            return "Fehler: Keine gültige Backup-Datei für '$target' im Ordner $timestamp gefunden.";
        }

        $restoredIn = [];

        // 3. Nach MySQL wiederherstellen
        if (\in_array($engine, ['all', 'mysql'], true) && $this->pdo instanceof \PDO) {
            $this->saveToSql($target, $data);
            $restoredIn[] = 'MySQL';
        }

        // 4. Nach JSON wiederherstellen
        if (\in_array($engine, ['all', 'json'], true)) {
            $this->saveToJson($target, $data);
            $restoredIn[] = 'JSON';
        }

        if (empty($restoredIn)) {
            return 'Hinweis: Es wurden keine Daten wiederhergestellt (Speicher nicht erreichbar).';
        }

        return "Erfolg: '$target' wurde aus Backup [$timestamp] in " . \implode(' & ', $restoredIn) . ' wiederhergestellt.';
    }

    /**
     * Leert den Deptrac-Cache und kompiliert die Session-Berechtigungen neu.
     *
     * @return string Statusmeldung.
     */
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

    /**
     * Löscht alle Daten eines Zielbereichs (Truncate) und erstellt vorher ein Backup.
     *
     * @param string $target Der zu leerende Speicherbereich.
     * @param string $engine 'all', 'json' oder 'mysql'.
     *
     * @return string Statusmeldung über den Vorgang.
     */
    public function truncateTarget(string $target, string $engine = 'all'): string
    {
        // 1. Zwingendes Backup vor der Löschung!
        try {
            // Backup erstellt sicherheitshalber immer beide Bestände
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

        // MySQL Tabelle leeren (Wenn engine 'all' oder 'mysql' ist)
        if (\in_array($engine, ['all', 'mysql'], true) && $this->pdo instanceof \PDO) {
            try {
                $tableName = $cfg['table'];
                $this->pdo->exec("TRUNCATE TABLE `$tableName`");
                $clearedIn[] = 'MySQL';
            } catch (\PDOException $e) {
                \error_log('Truncate Error MySQL: ' . $e->getMessage());
            }
        }

        // JSON Datei leeren (Wenn engine 'all' oder 'json' ist)
        $path = $this->config->getStoragePath($target);
        if (\in_array($engine, ['all', 'json'], true) && \file_exists($path)) {
            $jsonFlags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE;
            $this->writeJsonSafely($path, [], $jsonFlags);
            $clearedIn[] = 'JSON';
        }

        if (empty($clearedIn)) {
            return 'Hinweis: Es konnte nichts gelöscht werden (Speicher nicht erreichbar).';
        }

        return "Erfolg: Der Bereich '$target' wurde geleert (" .
            \implode(' & ', $clearedIn) .
            '). Ein Backup wurde erstellt.';
    }

    // --- Private Execution Routes ---

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
            $json  = new JsonStorage($this->config->getStoragePath('permits'));
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
            $json  = new JsonStorage($this->config->getStoragePath('permits'));
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
            $json = new JsonStorage($this->config->getStoragePath('permits'));
            $sql  = new MySqlStorage($this->pdo);

            $jsonPermits = $json->getAll();
            $sqlPermits  = $sql->getAll();

            $count = 0;
            // SQL nach JSON syncen
            foreach ($sqlPermits as $permit) {
                $json->save($permit);
                ++$count;
            }
            // JSON nach SQL syncen
            foreach ($jsonPermits as $permit) {
                $sql->save($permit);
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

    // --- Private Raw Data Loaders ---

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
        $path = $this->config->getStoragePath($key);

        return JsonHelper::read($path);
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
                $decoded   = JsonHelper::decode($r['data']);
                $r['data'] = $decoded !== null ? $decoded : [];
            }

            if (isset($r['permissions']) && \is_string($r['permissions'])) {
                $decoded          = JsonHelper::decode($r['permissions']);
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

    // --- Private Data Savers & Hydrators ---

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
        $this->writeJsonSafely($this->config->getStoragePath($key), $data, $jsonFlags);
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
        // [x] Sortiert
        match ($key) {
            'groups'               => $this->groupRepository->saveAll($data, true),
            'magic_links'          => $this->magicLinkRepository->saveAll($data, true),
            'mail_log'             => $this->mailLog->saveLogs($data, true),
            'mail_queue'           => $this->migrateMailQueueToSql($data),
            'pending_verification' => $this->verificationRepository->savePending($data, true),
            'permits_archive'      => $this->archiveRepository->archivePermits(0, $data),
            'permits'              => $this->migratePermitsToSql($data),
            'update_migrations'    => $this->migrateUpdateMigrationsToSql($data),
            'users'                => $this->userRepository->saveAll($data, true),
            'verified_pending'     => $this->verificationRepository->saveVerified($data, true),
            'vouchers_archive'     => $this->migrateVouchersArchiveToSql($data),
            'vouchers'             => $this->voucherRepository->saveAll($data, true),
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
            $storage->save($this->storage->mapToEntity($item));
        }
    }

    /**
     * Migriert den Protokoll-Verlauf ausgeführter System-Updates in die MySQL-Datenbank.
     *
     * @param array<int|string, array<string, mixed>> $data Rohdaten der bisherigen Updates.
     */
    private function migrateUpdateMigrationsToSql(array $data): void
    {
        if (! $this->pdo instanceof \PDO) {
            return;
        }
        $table = $this->config->get('storage_config')['update_migrations']['table'];
        $stmt  = $this->pdo->prepare("REPLACE INTO `$table` (id, version, executed_at) VALUES (?, ?, ?)");
        foreach ($data as $id => $item) {
            $stmt->execute([
                $id,
                $item['version'] ?? '',
                $item['executed_at'] ?? '',
            ]);
        }
    }

    /**
     * Migriert das Gutschein-Archiv (Vouchers) in die MySQL-Datenbank.
     *
     * @param array<int|string, array<string, mixed>> $data Rohdaten aus der Quelle.
     */
    private function migrateVouchersArchiveToSql(array $data): void
    {
        if (! $this->pdo instanceof \PDO) {
            return;
        }
        $table = $this->config->get('storage_config')['vouchers_archive']['table'];
        $stmt  = $this->pdo->prepare("REPLACE INTO `$table` (
            id,
            code,
            redeemed_at,
            user_name,
            user_plot
        ) VALUES (?, ?, ?, ?, ?)");

        foreach ($data as $id => $item) {
            $stmt->execute([
                $id,
                $item['code'] ?? '',
                $item['redeemed_at'] ?? '',
                $item['user_name'] ?? '',
                $item['user_plot'] ?? '',
            ]);
        }
    }

    /**
     * Migriert die E-Mail-Warteschlange (Mail Queue) in die MySQL-Datenbank.
     *
     * @param array<int|string, array<string, mixed>> $data Rohdaten aus der Quelle.
     */
    private function migrateMailQueueToSql(array $data): void
    {
        if (! $this->pdo instanceof \PDO) {
            return;
        }
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $stmt  = $this->pdo->prepare("REPLACE INTO `$table` (
            id,
            recipient,
            subject,
            template,
            data,
            attempts,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($data as $id => $item) {
            $payload = $item['data'] ?? [];
            $stmt->execute([
                $id,
                $item['recipient'] ?? '',
                $item['subject'] ?? '',
                $item['template'] ?? '',
                \is_array($payload) ? \json_encode($payload, \JSON_UNESCAPED_UNICODE) : $payload,
                (int) ($item['attempts'] ?? 0),
                $item['created_at'] ?? '',
            ]);
        }
    }

    // --- Private Utilities ---

    /**
     * Ermittelt den Namen der Primärschlüssel-Spalte (ID) für einen bestimmten Speicherbereich.
     *
     * @param string $key Der Schlüssel des Speicherbereichs.
     *
     * @return string Der Name der Spalte ('id', 'token' oder 'code').
     */
    private function getIdFieldForKey(string $key): string
    {
        // [x] Sortiert
        return match ($key) {
            'groups'               => 'id',
            'mail_log'             => 'id',
            'mail_queue'           => 'id',
            'update_migrations'    => 'id',
            'users'                => 'id',
            'vouchers_archive'     => 'id',
            'magic_links'          => 'token',
            'pending_verification' => 'token',
            'verified_pending'     => 'token',
            'permits_archive'      => 'code',
            'permits'              => 'code',
            default                => 'code'
        };
    }
}
