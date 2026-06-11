<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;

/**
 * Implementierung des Verification-Repositories (Warteraum).
 * Hält Anträge zwischen, deren E-Mail-Adresse (Opt-In) noch nicht bestätigt wurde
 * oder die noch auf den Abschluss einer PayPal-Zahlung warten.
 *
 * Path: src/Infrastructure/Storage/VerificationRepository.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationRepository implements VerificationRepositoryInterface
{
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    // --- Public API ---

    /**
     * Lädt ausstehende E-Mail-Verifizierungen und filtert abgelaufene (TTL) heraus.
     *
     * @return array<string, array<string, mixed>> Ausstehende Anträge.
     */
    public function loadPending(): array
    {
        $data   = $this->loadJson('pending_verification');
        $nowStr = \date('Y-m-d H:i:s');

        return \array_filter($data, fn (array $item): bool => isset($item['expires']) && $item['expires'] > $nowStr);
    }

    /**
     * Speichert unbestätigte Anträge ab.
     *
     * @param array<string, array<string, mixed>> $data     Daten.
     * @param bool                                $forceSql Erzwingt MySQL.
     */
    public function savePending(array $data, bool $forceSql = false): void
    {
        $this->saveJson('pending_verification', $data, $forceSql);
    }

    /**
     * Lädt E-Mail-bestätigte Anträge, die auf Zahlung warten.
     *
     * @return array<string, array<string, mixed>> Bestätigte Anträge.
     */
    public function loadVerified(): array
    {
        return $this->loadJson('verified_pending');
    }

    /**
     * Speichert Anträge ab, die auf Zahlung warten.
     *
     * @param array<string, array<string, mixed>> $data     Daten.
     * @param bool                                $forceSql Erzwingt MySQL.
     */
    public function saveVerified(array $data, bool $forceSql = false): void
    {
        $this->saveJson('verified_pending', $data, $forceSql);
    }

    // --- Private Helpers ---

    /**
     * Interne Methode zum dynamischen Laden von temporären Warteraum-Daten
     * aus MySQL oder JSON, inklusive Timestamp-Konvertierung und Filterung abgelaufener Tokens.
     *
     * @param  string                              $targetKey Der Schlüssel ('pending_verification' oder 'verified_pending').
     * @return array<string, array<string, mixed>> Die formatierten Daten.
     */
    private function loadJson(string $targetKey): array
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $data = [];

        if ($cfg['type'] === 'mysql') {
            if ($this->pdo instanceof \PDO) {
                $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $data[$r['token']]            = \json_decode((string) $r['data'], true);
                    $data[$r['token']]['expires'] = $r['expires'];
                }
            }
        } else {
            $path = $this->getFilePath($cfg['file']);
            if (\file_exists($path)) {
                $data = (array) \json_decode((string) \file_get_contents($path), true) ?? [];
            }
        }

        // On-the-fly Konvertierung alter Integer-Timestamps
        foreach ($data as &$item) {
            if (isset($item['expires']) && \is_numeric($item['expires'])) {
                $item['expires'] = \date('Y-m-d H:i:s', (int) $item['expires']);
            }
        }

        return $data;
    }

    /**
     * Interne Methode zum Schreiben temporärer Antragssitzungen.
     * Konvertiert Timestamps und schreibt in MySQL oder JSON.
     *
     * @param string                              $targetKey Der Schlüssel ('pending_verification' oder 'verified_pending').
     * @param array<string, array<string, mixed>> $data      Die abzuspeichernden Daten.
     * @param bool                                $forceSql  Erzwingt das Speichern in MySQL.
     */
    private function saveJson(string $targetKey, array $data, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')[$targetKey];
        $useSql = $forceSql || ($cfg['type'] === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (token, expires, data) VALUES (?, ?, ?)");
            foreach ($data as $token => $item) {
                $exp = $item['expires'] ?? \date('Y-m-d H:i:s');
                if (\is_numeric($exp)) {
                    $exp = \date('Y-m-d H:i:s', (int) $exp);
                }
                $stmt->execute([$token, $exp, \json_encode($item, \JSON_UNESCAPED_UNICODE)]);
            }
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = $this->getFilePath($cfg['file']);
            \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Löst den physischen Dateipfad basierend auf der Konfiguration auf.
     *
     * @param string $fileName Name der JSON-Datei.
     *
     * @return string Der absolute Pfad.
     */
    private function getFilePath(string $fileName): string
    {
        return \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
               \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $fileName;
    }
}
