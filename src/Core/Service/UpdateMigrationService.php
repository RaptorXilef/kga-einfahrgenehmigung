<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zur Ausführung von Datenbank- und Struktur-Updates (Migrationen).
 *
 * Path: src/Core/Service/MigrationService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UpdateMigrationService
{
    public function __construct(
        private ConfigInterface $config,
        private ?\PDO $pdo = null,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Sucht nach neuen Migrations-Skripten und führt diese aus.
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
     * TODO DOCBLOCK
     * Holt eine Liste aller bereits ausgeführten Versionen.
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
        $path = \rtrim($this->config->get('root_path'), '/') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        if (! \file_exists($path)) {
            return [];
        }

        $data = \json_decode((string) \file_get_contents($path), true) ?? [];

        return \array_column($data, 'version');
    }

    /**
     * TODO DOCBLOCK
     * Markiert ein Skript als "erledigt", damit es nie wieder läuft.
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
        $path = \rtrim($this->config->get('root_path'), '/') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        $data = \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];

        $data[] = [
            'id'          => \uniqid('mig_', true),
            'version'     => $version,
            'executed_at' => $now,
        ];

        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }
}
