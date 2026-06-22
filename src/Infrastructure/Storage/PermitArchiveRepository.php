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
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    use SafeJsonWriterTrait;
    use StorageMapperTrait;

    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    // --- Public API ---

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
            $stmt = $this->pdo->prepare("SELECT code FROM `{$arcCfg['table']}` WHERE code = ?");
            $stmt->execute([$code]);

            return (bool) $stmt->fetch();
        }

        $archivePath = $this->config->getStoragePath($arcCfg['file']);
        if (\file_exists($archivePath)) {
            $archiveData = JsonHelper::read($archivePath);

            return isset($archiveData[$code]);
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
            $sql = "REPLACE INTO `{$arcCfg['table']}` (
                code, template_key, name, email, kennzeichen, parzelle, typ,
                firma, zweck, preis, von, bis, status, erstellt, interner_kommentar,
                is_anonymized, agreements
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";
            $stmt = $this->pdo->prepare($sql);
            foreach ($permitsToArchive as $permit) {
                // Wandelt das Objekt direkt vor dem Speichern in ein Array um
                $item = $this->flattenEntity($permit);
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
                    $item['is_anonymized'] ?? 0,
                    $item['agreements'] ?? '{}',
                ]);
            }
        } else {
            $archivePath = $this->config->getStoragePath($arcCfg['file']);
            $existing    = \file_exists($archivePath) ? JsonHelper::read($archivePath) : [];

            // Mit Array-Keys arbeiten für schnelles Überschreiben
            foreach ($permitsToArchive as $permit) {
                // Hier nutzen wir flattenEntity für die JSON-Datei
                $existing[$permit->code] = $this->flattenEntity($permit);
            }

            $this->writeJsonSafely($archivePath, $existing);
        }
    }

    /**
     * Anonymisiert nach DSGVO-Vorgaben alte Archiv-Einträge (Aufbewahrungsfrist).
     *
     * @param int $yearsThreshold Die Aufbewahrungsfrist in Jahren (Standard: 10).
     *
     * @return int Die Anzahl der anonymisierten Datensätze.
     */
    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $arcCfg          = $this->config->get('storage_config')['permits_archive'];
        $cutoffDate      = \date('Y-m-d H:i:s', \strtotime("-{$yearsThreshold} years", APP_REQUEST_TIME));
        $anonymizedCount = 0;

        if ($arcCfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $sql = "UPDATE `{$arcCfg['table']}`
                    SET name = '[ANONYMISIERT]',
                        email = '[ANONYMISIERT]',
                        kennzeichen = '[ANONYMISIERT]',
                        parzelle = '0000',
                        is_anonymized = 1
                    WHERE erstellt <= ? AND is_anonymized = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cutoffDate]);
            $anonymizedCount = $stmt->rowCount();
        } else {
            $archivePath = $this->config->getStoragePath($arcCfg['file']);
            if (\file_exists($archivePath)) {
                $existing = JsonHelper::read($archivePath);
                $changed  = false;

                foreach ($existing as $code => &$item) {
                    if (isset($item['erstellt']) && $item['erstellt'] <= $cutoffDate && empty($item['is_anonymized'])) {
                        $item['name']          = '[ANONYMISIERT]';
                        $item['email']         = '[ANONYMISIERT]';
                        $item['kennzeichen']   = '[ANONYMISIERT]';
                        $item['parzelle']      = '0000';
                        $item['is_anonymized'] = 1;
                        $changed               = true;
                        ++$anonymizedCount;
                    }
                }

                if ($changed) {
                    $this->writeJsonSafely($archivePath, $existing);
                }
            }
        }

        return $anonymizedCount;
    }

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $key => $item) {
            if (! isset($item['code'])) {
                $item['code'] = $key;
            }
            $objects[] = $this->mapToEntity($item);
        }
        $this->archivePermits(0, $objects);
    }
}
