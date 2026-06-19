<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
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
        private ConfigInterface $config,
        private ?\PDO $pdo = null,
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
        $executed      = $this->getExecutedMigrations();
        $migrationsDir = $this->config->get('root_path') . '/src/Infrastructure/UpdateMigrations';
        $executedNow   = [];

        if (! \is_dir($migrationsDir)) {
            return $executedNow;
        }

        // Alle .php Dateien im Ordner suchen
        $files = \glob($migrationsDir . '/*.php');
        \sort($files); // WICHTIG: Chronologisch sortieren (z.B. 001_update.php, 002_update.php)

        foreach ($files as $file) {
            $version = \basename($file, '.php');

            // Wenn diese Version noch nicht ausgeführt wurde
            if (! \in_array($version, $executed, true)) {

                // Wir erwarten, dass die Datei eine anonyme Funktion (Closure) zurückgibt
                $migrationClosure = require $file;

                if (\is_callable($migrationClosure)) {
                    // Führe die Migration aus und übergebe PDO & Config für volle Flexibilität
                    $migrationClosure($this->pdo, $this->config);

                    // Erfolgreich ausgeführt -> in DB/JSON eintragen
                    $this->markAsExecuted($version);
                    $executedNow[] = $version;
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
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO `{$cfg['table']}` (`version`, `executed_at`) VALUES (?, ?)");
            $stmt->execute([$version, $now]);

            return;
        }

        // JSON Speicherung
        $path = $this->config->getStoragePath($cfg['file']);
        $data = JsonHelper::read($path);

        $data[] = [
            'id'          => \uniqid('mig_', true),
            'version'     => $version,
            'executed_at' => $now,
        ];

        $this->writeJsonSafely($path, $data);
    }
}
