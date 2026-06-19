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
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherRepository implements VoucherRepositoryInterface
{
    use SafeJsonWriterTrait;

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
                $r['data'] = \is_string($r['data']) ? JsonHelper::decode($r['data']) : $r['data'];
                $r['data'] ??= [];
                $vouchers[$r['code']] = $r;
            }

            return $vouchers;
        }

        $path = $this->config->getStoragePath($cfg['file']);

        return JsonHelper::read($path);
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
        $path = $this->config->getStoragePath($cfg['file']);

        return JsonHelper::read($path);
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
            $this->pdo->beginTransaction(); // <-- FIX: Transaktion starten

            try {
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
                        'created_at'   => $v['created_at'] ?? APP_REQUEST_TIME_STR,
                        'status'       => $v['status'] ?? 'aktiv',
                        'data'         => \is_array($v['data'] ?? null) ? \json_encode($v['data'], \JSON_UNESCAPED_UNICODE) : '{}',
                    ]);
                }
                $this->pdo->commit(); // <-- FIX: Bei Erfolg speichern
            } catch (\Exception $e) {
                $this->pdo->rollBack(); // <-- FIX: Bei Fehler Zustand wiederherstellen

                throw $e;
            }
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = $this->config->getStoragePath($cfg['file']);
            $this->writeJsonSafely($path, $vouchers);
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
            $archivePath = $this->config->getStoragePath($arcCfg['file']);
            $archive     = JsonHelper::read($archivePath);
            $archive[]   = $archiveEntry;
            $this->writeJsonSafely($archivePath, $archive);
        }
    }
}
