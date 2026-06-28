<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Infrastructure\Storage\JsonHelper;
use App\Infrastructure\Storage\SafeJsonWriterTrait;

/**
 * Service zur Ausführung von Datenbank- und Struktur-Updates (Migrationen).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UpdateMigrationService
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ?\PDO $pdo, // <-- FIX: "= null" entfernt
        private ClockInterface $clock,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Public API: Startet die Update-Kette
     *
     * Sucht nach neuen Migrations-Skripten im Ordner und führt diese chronologisch aus.
     *
     * @return array<int, string> Liste der neu ausgeführten Migrations-Versionen.
     */
    public function runAllPending(): array
    {
        $executed = $this->getExecutedMigrations();

        // Nutze den garantierten Root-Pfad aus der Config
        $migrationsDir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/src/Infrastructure/UpdateMigrations';
        $executedNow   = [];

        if (! \is_dir($migrationsDir)) {
            \error_log('Migration Error: Ordner nicht gefunden: ' . $migrationsDir);

            return $executedNow;
        }

        // Wir erzwingen das korrekte Slashen für Windows UND Linux
        $files = \glob($migrationsDir . \DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            return $executedNow;
        }

        \sort($files); // WICHTIG: Chronologisch sortieren (z.B. 001_update.php, 002_update.php)

        foreach ($files as $file) {
            $version = \basename($file, '.php');

            // Wenn diese Version noch nicht ausgeführt wurde
            if (! \in_array($version, $executed, true)) {
                try {
                    // Wir erwarten, dass die Datei eine anonyme Funktion (Closure) zurückgibt
                    $migrationClosure = require $file;

                    if (\is_callable($migrationClosure)) {
                        // Führe die Closure aus der Datei aus
                        $migrationClosure($this->pdo, $this->config);

                        // Erfolgreich ausgeführt -> in DB/JSON eintragen
                        $this->markAsExecuted($version);
                        $executedNow[] = $version;
                    } else {
                        \error_log("Migration Error: Datei {$version}.php liefert keine Closure zurück.");
                    }
                } catch (\Throwable $e) {
                    \error_log("Kritischer Fehler bei Migration {$version}: " . $e->getMessage());
                }
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
        if (! $cfg) {
            return [];
        }

        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            try {
                $stmt = $this->pdo->query("SELECT `version` FROM `{$cfg['table']}`");

                return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            } catch (\PDOException $e) {
                // Tabelle existiert ggf. bei der allerersten Installation noch nicht
                return [];
            }
        }

        // JSON Fallback
        $path = $this->config->getStoragePath($cfg['file']);
        if (! \file_exists($path)) {
            return [];
        }

        $data = JsonHelper::read($path);

        return \array_column($data, 'version');
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
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            // Hier muss zwingend eine ID übergeben werden, da MySQL sonst blockiert!
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO `{$cfg['table']}` (`id`, `version`, `executed_at`) VALUES (?, ?, ?)");
            $stmt->execute([\uniqid('mig_', true), $version, $now]);

            return;
        }

        // JSON Speicherung
        $path = $this->config->getStoragePath($cfg['file']);
        // Prüfen, ob Datei existiert, bevor wir den strengen JsonHelper nutzen!
        $data = \file_exists($path) ? JsonHelper::read($path) : [];

        $data[] = [
            'id'          => \uniqid('mig_', true),
            'version'     => $version,
            'executed_at' => $now,
        ];
        $this->writeJsonSafely($path, $data);
    }

    public function import(array $data, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['update_migrations'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                $stmt = $this->pdo->prepare("REPLACE INTO `{$cfg['table']}` (id, version, executed_at) VALUES (?, ?, ?)");
                foreach ($data as $id => $item) {
                    $stmt->execute([$id, $item['version'] ?? '', $item['executed_at'] ?? '']);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
        } elseif (! $forceSql) {
            $path = $this->config->getStoragePath($cfg['file']);
            $this->writeJsonSafely($path, \array_values($data));
        }
    }
}
