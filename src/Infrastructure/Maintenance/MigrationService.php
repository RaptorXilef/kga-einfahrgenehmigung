<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Storage\JsonAuditLogRepository;
use App\Infrastructure\Storage\JsonCancelledPermitRepository;
use App\Infrastructure\Storage\JsonGroupRepository;
use App\Infrastructure\Storage\JsonLoginAttemptRepository;
use App\Infrastructure\Storage\JsonMagicLinkRepository;
use App\Infrastructure\Storage\JsonMailQueueRepository;
use App\Infrastructure\Storage\JsonPermitArchiveRepository;
use App\Infrastructure\Storage\JsonStorage;
use App\Infrastructure\Storage\JsonUserRepository;
use App\Infrastructure\Storage\JsonVerificationRepository;
use App\Infrastructure\Storage\JsonVoucherRepository;
use App\Infrastructure\Storage\MySqlAuditLogRepository;
use App\Infrastructure\Storage\MySqlCancelledPermitRepository;
use App\Infrastructure\Storage\MySqlGroupRepository;
use App\Infrastructure\Storage\MySqlLoginAttemptRepository;
use App\Infrastructure\Storage\MySqlMagicLinkRepository;
use App\Infrastructure\Storage\MySqlMailQueueRepository;
use App\Infrastructure\Storage\MySqlPermitArchiveRepository;
use App\Infrastructure\Storage\MySqlStorage;
use App\Infrastructure\Storage\MySqlUserRepository;
use App\Infrastructure\Storage\MySqlVerificationRepository;
use App\Infrastructure\Storage\MySqlVoucherRepository;
use App\Infrastructure\Storage\SafeJsonWriterTrait;
use App\Infrastructure\Utils\SystemClock;

/**
 * Service für Daten-Migrationen, automatisierte Datensicherungen (Backups) und System-Recovery.
 *
 * Überträgt relationale und flache Dateistrukturen (JSON <-> MySQL) bidirektional,
 * steuert automatische Backup-Zyklen und stellt Tabellen-Schemata sowie Initialdaten (Seeding) her.
 * Kontext: Administrativer Wartungs- und Backup-Manager der Anwendung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MigrationService implements MigrationServiceInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ?\PDO $pdo,
        private AuthService $authService,
        private BackupService $backupService,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
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
        try {
            $backupFolder = $this->backupService->createBackup($target);
        } catch (\Exception $e) {
            return 'Abbruch: Backup konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        if (! $this->pdo && \str_contains($action, 'mysql')) {
            return 'Fehler: MySQL-Server ist nicht erreichbar.';
        }

        try {
            // Logik für das "Alles Migrieren"
            if ($target === 'all') {
                $targetsToMigrate = [
                    'groups',
                    'login_attempts',
                    'magic_links',
                    'mail_log',
                    'mail_queue',
                    'pending_verification',
                    'permits',
                    'permits_archive',
                    'permits_cancelled',
                    'update_migrations',
                    'users',
                    'verified_pending',
                    'vouchers',
                    'vouchers_archive',
                    'audit_logs',
                ];

                $count = 0;
                foreach ($targetsToMigrate as $t) {
                    match ($action) {
                        'json_to_mysql' => $this->migrateJsonToSql($t),
                        'mysql_to_json' => $this->migrateSqlToJson($t),
                        'sync'          => $this->syncBoth($t),
                        default         => null,
                    };
                    ++$count;
                }

                return "Voll-Backup erstellt in $backupFolder. <br>Erfolg: $count Bereiche komplett migriert ($action).";
            }

            // Bisherige Einzel-Logik
            $result = match ($action) {
                'json_to_mysql' => $this->migrateJsonToSql($target),
                'mysql_to_json' => $this->migrateSqlToJson($target),
                'sync'          => $this->syncBoth($target),
                default         => 'Fehler: Unbekannte Aktion.'
            };

            return "Backup erstellt in $backupFolder. <br>" . $result;
        } catch (\Throwable $e) {
            // Fehler direkt in die Log-Datei schreiben...
            \error_log("Migration Error ({$target} / {$action}): " . $e->getMessage() . "\n" . $e->getTraceAsString());

            // ... und als rote Meldung ins Dashboard zurückgeben!
            return 'Kritischer Fehler bei der Migration: ' . $e->getMessage();
        }
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
                $tableName     = $cfg['table'];
                $allowedTables = \array_column($this->config->get('storage_config'), 'table');

                if (! \in_array($tableName, $allowedTables, true)) {
                    throw new \RuntimeException('Sicherheitsabbruch: Tabellenname nicht in Config autorisiert.');
                }

                $this->pdo->exec("TRUNCATE TABLE `$tableName`");
                $clearedIn[] = 'MySQL';
            } catch (\PDOException $e) {
                \error_log('Truncate Error MySQL: ' . $e->getMessage());
            }
        }

        // JSON Datei leeren (Wenn engine 'all' oder 'json' ist)
        if (\in_array($engine, ['all', 'json'], true) && isset($cfg['file'])) {
            $path      = $this->config->getStoragePath($cfg['file']);
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
     *
     * @param string $target Der zu exportierende Bereich.
     *
     * @return string Statusmeldung über die Anzahl exportierter Datensätze.
     */
    private function migrateSqlToJson(string $target): string
    {
        if ($target === 'permits') {
            // Sonderbehandlung für Genehmigungen wegen Entity-Mapping
            $file  = $this->config->get('storage_config')['permits']['file'] ?? 'permits.json';
            $json  = new JsonStorage($this->config->getStoragePath($file), $this->jsonHelper);
            $sql   = new MySqlStorage($this->pdo, $this->jsonHelper);
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
            $file  = $this->config->get('storage_config')['permits']['file'] ?? 'permits.json';
            $json  = new JsonStorage($this->config->getStoragePath($file), $this->jsonHelper);
            $sql   = new MySqlStorage($this->pdo, $this->jsonHelper);
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
            $file        = $this->config->get('storage_config')['permits']['file'] ?? 'permits.json';
            $json        = new JsonStorage($this->config->getStoragePath($file), $this->jsonHelper);
            $sql         = new MySqlStorage($this->pdo, $this->jsonHelper);
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
        $cfg = $this->config->get('storage_config')[$key] ?? null;

        if (! isset($cfg['file'])) {
            return [];
        }

        $path = $this->config->getStoragePath($cfg['file']);

        return $this->jsonHelper->read($path);
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
                // \error_log("Bootstrap: MySQL-Tabelle `$tableName` ist leer.");

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
                $decoded   = $this->jsonHelper->decode($r['data']);
                $r['data'] = $decoded !== null ? $decoded : [];
            }

            if (isset($r['permissions']) && \is_string($r['permissions'])) {
                $decoded          = $this->jsonHelper->decode($r['permissions']);
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

    /**
     * Routet Rohdaten an die zuständigen Service-Klassen zur korrekten SQL-Persistierung weiter.
     * Nutzt einen generischen Fallback für Tabellen ohne spezifischen Service.
     */
    private function saveToSql(string $key, array $data): void
    {
        if (! $this->pdo instanceof \PDO) {
            return;
        }

        match ($key) {
            'audit_logs'           => (new MySqlAuditLogRepository($this->pdo, $this->config))->import($data),
            'groups'               => (new MySqlGroupRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'users'                => (new MySqlUserRepository($this->pdo, $this->config))->import($data),
            'login_attempts'       => (new MySqlLoginAttemptRepository($this->pdo, $this->config))->import($data),
            'magic_links'          => (new MySqlMagicLinkRepository($this->pdo, $this->config))->import($data),
            'mail_log'             => (new SmtpMailService($this->pdo, $this->config, $this->jsonHelper))->importLogs($data, true),
            'mail_queue'           => (new MySqlMailQueueRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'pending_verification' => (new MySqlVerificationRepository($this->pdo, $this->config, $this->jsonHelper))->savePending($data, true),
            'permits'              => (new MySqlStorage($this->pdo, $this->jsonHelper))->import($data),
            'permits_archive'      => (new MySqlPermitArchiveRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'permits_cancelled'    => (new MySqlCancelledPermitRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'update_migrations'    => (new UpdateMigrationService($this->pdo, new SystemClock(), $this->config, $this->jsonHelper))->import($data, true),
            'verified_pending'     => (new MySqlVerificationRepository($this->pdo, $this->config, $this->jsonHelper))->saveVerified($data, true),
            'vouchers'             => (new MySqlVoucherRepository($this->pdo, $this->config, $this->jsonHelper))->saveAll($data, true),
            'vouchers_archive'     => (new MySqlVoucherRepository($this->pdo, $this->config, $this->jsonHelper))->importArchive($data),
            default                => throw new \InvalidArgumentException("Kein SQL-Mapper für Speicherbereich '$key' definiert.")
        };
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
        match ($key) {
            'audit_logs'           => (new JsonAuditLogRepository($this->config, $this->jsonHelper))->import($data),
            'groups'               => (new JsonGroupRepository($this->config, $this->jsonHelper))->import($data),
            'users'                => (new JsonUserRepository($this->config, $this->jsonHelper))->import($data),
            'login_attempts'       => (new JsonLoginAttemptRepository($this->config, $this->jsonHelper))->import($data),
            'magic_links'          => (new JsonMagicLinkRepository($this->config, $this->jsonHelper))->import($data),
            'mail_log'             => (new SmtpMailService($this->pdo, $this->config, $this->jsonHelper))->importLogs($data, false),
            'mail_queue'           => (new JsonMailQueueRepository($this->config, $this->jsonHelper))->import($data),
            'pending_verification' => (new JsonVerificationRepository($this->config, $this->jsonHelper))->savePending($data, false),
            'permits'              => (new JsonStorage($this->config->getStoragePath($this->config->get('storage_config')['permits']['file'] ?? 'permits.json'), $this->jsonHelper))->import($data),
            'permits_archive'      => (new JsonPermitArchiveRepository($this->config, $this->jsonHelper))->import($data),
            'permits_cancelled'    => (new JsonCancelledPermitRepository($this->config, $this->jsonHelper))->import($data),
            'update_migrations'    => (new UpdateMigrationService($this->pdo, new SystemClock(), $this->config, $this->jsonHelper))->import($data, false),
            'verified_pending'     => (new JsonVerificationRepository($this->config, $this->jsonHelper))->saveVerified($data, false),
            'vouchers'             => (new JsonVoucherRepository($this->config, $this->jsonHelper))->saveAll($data, false),
            'vouchers_archive'     => (new JsonVoucherRepository($this->config, $this->jsonHelper))->importArchive($data),
            default                => throw new \InvalidArgumentException("Kein JSON-Mapper für Speicherbereich '$key' definiert.")
        };
    }

    /**
     * Ermittelt den Namen der Primärschlüssel-Spalte (ID) für einen bestimmten Speicherbereich.
     *
     * @param string $key Der Schlüssel des Speicherbereichs.
     *
     * @return string Der Name der Spalte ('id', 'token' oder 'code').
     */
    private function getIdFieldForKey(string $key): string
    {
        return match ($key) {
            'audit_logs'           => 'id',
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
            'permits_cancelled'    => 'code',
            'permits'              => 'code',
            default                => 'code'
        };
    }
}
