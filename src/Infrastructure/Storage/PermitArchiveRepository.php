<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;

/**
 * Implementierung des Permit-Archive-Repositories.
 * Schiebt alte, abgelaufene Genehmigungen in ein separates Langzeit-Archiv,
 * um die Hauptdatenbank / das Haupt-JSON klein und performant zu halten.
 *
 * Path: src/Infrastructure/Storage/PermitArchiveRepository.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Überprüft die systemweite Einzigartigkeit eines Genehmigungs-Codes.
     * Scannt hierzu das aktive SQL-Archiv oder alle historischen JSON-Jahresarchive
     * auf der Festplatte, um Duplikate auszuschließen.
     *
     * @param string $code Der zu prüfende Gesamt-Code.
     *
     * @return bool True, wenn der Code bereits im Archiv existiert.
     */
    public function isCodeInArchive(string $code): bool
    {
        $arcCfg = $this->config->get('storage_config')['permits_archive'];

        if ($arcCfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare("SELECT code FROM {$arcCfg['table']} WHERE code = ?");
            $stmt->execute([$code]);

            return (bool) $stmt->fetch();
        }

        $storageDir  = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix');
        $globPattern = \str_replace('{YEAR}', '*', (string) $arcCfg['file_pattern']);
        $archives    = \glob($storageDir . $globPattern);

        if ($archives !== false) {
            foreach ($archives as $archivePath) {
                $archiveData = \json_decode((string) \file_get_contents($archivePath), true) ?? [];
                if (isset($archiveData[$code])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verschiebt eine Liste von abgelaufenen Genehmigungen in das Langzeit-Archiv.
     * Speichert die Daten je nach Konfiguration in einer SQL-Tabelle oder in jahresbasierten JSON-Dateien.
     *
     * @param int                  $year             Das Jahr, dem die Archivdaten zugeordnet werden sollen.
     * @param array<string, mixed> $permitsToArchive Die zu archivierenden Datensätze.
     */
    public function archivePermits(int $year, array $permitsToArchive): void
    {
        if (empty($permitsToArchive)) {
            return;
        }

        $arcCfg = $this->config->get('storage_config')['permits_archive'];

        if ($arcCfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $sql = "REPLACE INTO {$arcCfg['table']} (
                code, template_key, name, email, kennzeichen, parzelle, typ,
                firma, zweck, preis, von, bis, status, erstellt, interner_kommentar
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";
            $stmt = $this->pdo->prepare($sql);
            foreach ($permitsToArchive as $item) {
                $stmt->execute([
                    $item['code'],
                    $item['template_key'],
                    $item['name'],
                    $item['email'],
                    $item['kennzeichen'],
                    $item['parzelle'],
                    $item['typ'],
                    $item['firma'],
                    $item['zweck'],
                    $item['preis'],
                    $item['von'],
                    $item['bis'],
                    $item['status'],
                    $item['erstellt'],
                    $item['interner_kommentar'],
                ]);
            }
        } else {
            $yearPath = \str_replace(
                '{YEAR}',
                (string) $year,
                $this->config->get('root_path') . '/' .
                    $this->config->get('storage_path_prefix') . $arcCfg['file_pattern'],
            );
            $existing = \file_exists($yearPath) ? (array) \json_decode(
                (string) \file_get_contents($yearPath),
                true,
            ) : [];
            \file_put_contents(
                $yearPath,
                \json_encode(
                    \array_merge($existing, $permitsToArchive),
                    \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE,
                ),
            );
        }
    }
}
