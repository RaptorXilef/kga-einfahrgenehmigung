<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;

/**
 * Implementierung des Voucher-Repositories.
 * Speichert aktive Gutscheincodes, deren Nutzungsstatistiken und
 * verschiebt vollständig eingelöste Rabattcodes ins Archiv.
 *
 * Path: src/Infrastructure/Storage/VoucherRepository.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VoucherRepository implements VoucherRepositoryInterface
{
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Lädt alle aktiven, einlösbaren Gutscheine aus dem konfigurierten Repository (MySQL oder JSON).
     *
     * @return array<string, array<string, mixed>> Assoziatives Array aller Gutscheine, indiziert nach Code.
     */
    public function loadAll(): array
    {
        $cfg = $this->config->get('storage_config')['vouchers'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo instanceof \PDO) {
                throw new \RuntimeException('Datenbank offline.');
            }
            $stmt     = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
            $vouchers = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $r['data'] = \is_string($r['data']) ? \json_decode($r['data'], true) : $r['data'];
                $r['data'] ??= [];
                $vouchers[$r['code']] = $r;
            }

            return $vouchers;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * Lädt alle historischen Protokolle bereits verbrauchter/eingelöster Gutscheine aus dem Archiv.
     *
     * @return array<int, array<string, mixed>> Zeitlich absteigend sortierte Liste der Einlösungs-Logs.
     */
    public function loadArchive(): array
    {
        $cfg = $this->config->get('storage_config')['vouchers_archive'];
        if ($cfg['type'] === 'mysql') {
            return $this->pdo->query("SELECT * FROM `{$cfg['table']}` ORDER BY redeemed_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
        }
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * Persistiert den vollständigen Gutschein-Livebestand im aktiven Speicher-Backend.
     *
     * @param array<string, array<string, mixed>> $vouchers Die zu speichernde Gutscheinliste.
     */
    public function saveAll(array $vouchers, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['vouchers'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $sql = "INSERT INTO `{$cfg['table']}` (
                code, reason, template_key, type, value, multi_use, max_uses,
                uses_count, expires_at, date_mode, created_by, created_at, status, data
            ) VALUES (
                :code, :reason, :template_key, :type, :value, :multi_use, :max_uses,
                :uses_count, :expires_at, :date_mode, :created_by, :created_at, :status, :data
            )";
            $stmt = $this->pdo->prepare($sql);

            foreach ($vouchers as $v) {
                $stmt->execute([
                    'code'         => $v['code'] ?? '',
                    'reason'       => $v['reason'] ?? '',
                    'template_key' => $v['template_key'] ?? 'std_7',
                    'type'         => $v['type'] ?? 'free',
                    'value'        => (float) ($v['value'] ?? 0.0),
                    'multi_use'    => (int) ($v['multi_use'] ?? 0),
                    'max_uses'     => (int) ($v['max_uses'] ?? 1),
                    'uses_count'   => (int) ($v['uses_count'] ?? 0),
                    'expires_at'   => $v['expires_at'] ?? null,
                    'date_mode'    => $v['date_mode'] ?? 'fixed',
                    'created_by'   => $v['created_by'] ?? '',
                    'created_at'   => $v['created_at'] ?? \date('Y-m-d H:i:s'),
                    'status'       => $v['status'] ?? 'aktiv',
                    'data'         => \is_array($v['data'] ?? null) ? \json_encode($v['data'], \JSON_UNESCAPED_UNICODE) : '{}',
                ]);
            }
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
            \file_put_contents($path, \json_encode($vouchers, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Fügt einen vollständig eingelösten Gutschein als Protokoll-Eintrag dem Archiv hinzu.
     * Schreibt den Eintrag entweder in die MySQL-Archivtabelle oder die entsprechende JSON-Datei.
     *
     * @param array<string, mixed> $archiveEntry Der hinzuzufügende Archiv-Datensatz.
     */
    public function appendToArchive(array $archiveEntry): void
    {
        $arcCfg = $this->config->get('storage_config')['vouchers_archive'];

        if ($arcCfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $sql  = "INSERT INTO `{$arcCfg['table']}` (code, redeemed_at, user_name, user_plot) VALUES (:code, :redeemed_at, :user_name, :user_plot)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'code'        => $archiveEntry['code'],
                'redeemed_at' => $archiveEntry['redeemed_at'],
                'user_name'   => $archiveEntry['user_name'],
                'user_plot'   => $archiveEntry['user_plot'],
            ]);
        } else {
            $archivePath = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $arcCfg['file'];
            $archive     = \file_exists($archivePath) ? \json_decode((string) \file_get_contents($archivePath), true) : [];
            $archive[]   = $archiveEntry;
            \file_put_contents($archivePath, \json_encode($archive, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
    }
}
