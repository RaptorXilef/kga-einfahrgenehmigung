<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlVoucherRepository implements VoucherRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg      = $this->config->get('storage_config')['vouchers'];
        $stmt     = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        $vouchers = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $r['data'] = \is_string($r['data']) ? JsonHelper::decode($r['data']) : $r['data'];
            $r['data'] ??= [];
            $vouchers[$r['code']] = $r;
        }

        return $vouchers;
    }

    public function saveAll(array $vouchers, bool $forceSql = false): void
    {
        $cfg = $this->config->get('storage_config')['vouchers'];
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $sql  = "INSERT INTO `{$cfg['table']}` (code, reason, template_key, type, value, multi_use, max_uses, uses_count, expires_at, date_mode, created_by, created_at, status, data) VALUES (:code, :reason, :template_key, :type, :value, :multi_use, :max_uses, :uses_count, :expires_at, :date_mode, :created_by, :created_at, :status, :data)";
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
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function loadArchive(): array
    {
        $cfg = $this->config->get('storage_config')['vouchers_archive'];

        return $this->pdo->query("SELECT * FROM `{$cfg['table']}` ORDER BY redeemed_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function appendToArchive(array $archiveEntry): void
    {
        $arcCfg = $this->config->get('storage_config')['vouchers_archive'];
        $sql    = "INSERT INTO `{$arcCfg['table']}` (code, redeemed_at, user_name, user_plot) VALUES (:code, :redeemed_at, :user_name, :user_plot)";
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute([
            'code'        => $archiveEntry['code'],
            'redeemed_at' => $archiveEntry['redeemed_at'],
            'user_name'   => $archiveEntry['user_name'],
            'user_plot'   => $archiveEntry['user_plot'],
        ]);
    }

    public function importArchive(array $data): void
    {
        $table = $this->config->get('storage_config')['vouchers_archive']['table'];
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("REPLACE INTO `$table` (id,code,redeemed_at,user_name,user_plot) VALUES (?, ?, ?, ?, ?)");
            foreach ($data as $id => $item) {
                $stmt->execute([$id, $item['code'] ?? '', $item['redeemed_at'] ?? '', $item['user_name'] ?? '', $item['user_plot'] ?? '']);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
