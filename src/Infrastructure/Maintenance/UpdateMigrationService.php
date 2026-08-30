<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use Exception;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Service zur Ausführung von Datenbank-Updates über reine SQL-Dateien.
 */
final readonly class UpdateMigrationService implements UpdateMigrationServiceInterface
{
    public function __construct(
        private ?PDO $pdo,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper, // Bleibt für Kompatibilität mit dem ServiceProvider erhalten
    ) {
    }

    /**
     * Sucht nach neuen .sql-Skripten im Ordner und führt diese chronologisch aus.
     * Fängt harmlose Überschneidungs-Fehler (z.B. mit dem StorageBootstrapper) sicher ab.
     *
     * @return array<int, string> Liste der neu ausgeführten Migrations-Versionen.
     */
    public function runAllPending(): array
    {
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('Fehler: Keine Datenbankverbindung für Migrationen vorhanden.');
        }

        $executed = $this->getExecutedMigrations();
        $migrationsDir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/database/migrations';
        $executedNow = [];

        if (!\is_dir($migrationsDir)) {
            \error_log('Migration: Ordner nicht gefunden: ' . $migrationsDir);

            return $executedNow;
        }

        $files = \glob($migrationsDir . \DIRECTORY_SEPARATOR . '*.sql');

        if ($files === false || $files === []) {
            return $executedNow;
        }

        \sort($files); // Chronologisch sortieren (z.B. 022_add_column.sql)

        foreach ($files as $file) {
            $version = \basename($file, '.sql');

            if (\in_array($version, $executed, true)) {
                continue;
            }

            try {
                $sql = \file_get_contents($file);

                if ($sql === false || \trim($sql) === '') {
                    throw new RuntimeException("Datei {$version}.sql ist leer oder nicht lesbar.");
                }

                // --- FIX: Statements sicher trennen ---
                // Wir trennen die SQL-Befehle am Semikolon auf.
                // Dadurch können wir jeden Befehl einzeln ausführen und gezielt "Already exists" Fehler abfangen,
                // die entstehen, wenn der StorageBootstrapper die Tabelle/Spalte bereits angelegt hat.
                $statements = \array_filter(\array_map('trim', \explode(';', $sql)));

                foreach ($statements as $statement) {
                    if ($statement === '') {
                        continue;
                    }

                    try {
                        $this->pdo->exec($statement);
                    } catch (PDOException $e) {
                        $mysqlCode = $e->errorInfo[1] ?? 0;

                        // Erwartete Kollisions-Fehler ignorieren:
                        // 1050 = Table already exists
                        // 1051 = Unknown table (z.B. beim Drop Table)
                        // 1060 = Duplicate column name
                        // 1061 = Duplicate key name
                        // 1146 = Table doesn't exist (Trifft z.B. bei RENAME TABLE auf bereits gelöschte Tabellen zu)
                        if (\in_array($mysqlCode, [1050, 1051, 1060, 1061, 1146], true)) {
                            continue; // Harmloser Fehler bei bereits aktualisiertem Schema
                        }

                        // Echte, unerwartete Fehler werfen wir weiter
                        throw $e;
                    }
                }

                $this->markAsExecuted($version);
                $executedNow[] = $version;
            } catch (Throwable $e) {
                \error_log("Kritischer Fehler bei SQL-Migration {$version}: " . $e->getMessage());

                throw new RuntimeException("SQL-Migration '{$version}' fehlgeschlagen: " . $e->getMessage(), 0, $e);
            }
        }

        return $executedNow;
    }

    /**
     * Private Helper: Liest den Ist-Zustand
     *
     * Holt eine Liste aller historisch bereits ausgeführten Versionen aus der Datenbank oder JSON.
     *
     * @return array<int, string>
     */
    private function getExecutedMigrations(): array
    {
        $cfg = $this->config->get('storage_config')['update_migrations'] ?? null;
        if (!$cfg || !$this->pdo instanceof PDO) {
            return [];
        }

        try {
            $stmt = $this->pdo->query("SELECT `version` FROM `{$cfg['table']}`");

            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Private Helper: Schreibt den Soll-Zustand
     *
     * Markiert ein Migrations-Skript als "erledigt", damit es bei zukünftigen Updates ignoriert wird.
     *
     * @param string $version Die Version/der Name des Skripts.
     */
    private function markAsExecuted(string $version): void
    {
        $cfg = $this->config->get('storage_config')['update_migrations'] ?? null;
        if (!$cfg || !$this->pdo instanceof PDO) {
            return;
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `{$cfg['table']}` (`id`, `version`, `executed_at`) VALUES (?, ?, ?)");
        $stmt->execute([\uniqid('mig_', true), $version, $now]);
    }

    public function import(array $data): void
    {
        $cfg = $this->config->get('storage_config')['update_migrations'] ?? null;
        if (!$cfg || !$this->pdo instanceof PDO) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("REPLACE INTO `{$cfg['table']}` (id, version, executed_at) VALUES (?, ?, ?)");
            foreach ($data as $id => $item) {
                $stmt->execute([$id, $item['version'] ?? '', $item['executed_at'] ?? '']);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
