<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Entity\Voucher;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlVoucherRepository implements VoucherRepositoryInterface
{
    use DynamicSqlTrait;

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
            $data    = \is_string($r['data']) ? JsonHelper::decode($r['data']) : ($r['data'] ?? []);
            $expires = $r['expires_at'] ? new \DateTimeImmutable($r['expires_at']) : null;
            $created = $r['created_at'] ? new \DateTimeImmutable($r['created_at']) : new \DateTimeImmutable();

            $vouchers[$r['code']] = new Voucher(
                $r['code'],
                $r['reason'],
                $r['template_key'],
                $r['type'],
                (float) $r['value'],
                (bool) $r['multi_use'],
                (int) $r['max_uses'],
                (int) $r['uses_count'],
                $expires,
                $r['date_mode'],
                $r['created_by'],
                $created,
                $r['status'],
                $data,
            );
        }

        return $vouchers;
    }

    public function saveAll(array $vouchers, bool $forceSql = false): void
    {
        $table = $this->config->get('storage_config')['vouchers']['table'];
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");

            $sql  = null;
            $stmt = null;

            foreach ($vouchers as $v) {
                $data = [
                    'code'         => $v->code,
                    'reason'       => $v->reason,
                    'template_key' => $v->templateKey,
                    'type'         => $v->type,
                    'value'        => $v->value,
                    'multi_use'    => (int) $v->multiUse,
                    'max_uses'     => $v->maxUses,
                    'uses_count'   => $v->usesCount,
                    'expires_at'   => $v->expiresAt?->format('Y-m-d H:i:s'),
                    'date_mode'    => $v->dateMode,
                    'created_by'   => $v->createdBy,
                    'created_at'   => $v->createdAt->format('Y-m-d H:i:s'),
                    'status'       => $v->status,
                    'data'         => \json_encode($v->data, \JSON_UNESCAPED_UNICODE),
                ];

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $data);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($data);
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
        $table = $this->config->get('storage_config')['vouchers_archive']['table'];

        $data = [
            'code'        => $archiveEntry['code'],
            'redeemed_at' => $archiveEntry['redeemed_at'],
            'user_name'   => $archiveEntry['user_name'],
            'user_plot'   => $archiveEntry['user_plot'],
        ];

        $sql = $this->buildReplaceSql($table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $code => $r) {
            $payload = \is_string($r['data'] ?? []) ? JsonHelper::decode($r['data']) : ($r['data'] ?? []);
            $expires = ! empty($r['expires_at']) ? new \DateTimeImmutable($r['expires_at']) : null;
            $created = ! empty($r['created_at']) ? new \DateTimeImmutable($r['created_at']) : new \DateTimeImmutable();

            $objects[$code] = new Voucher(
                (string) $code,
                $r['reason'] ?? '',
                $r['template_key'] ?? 'std_7',
                $r['type'] ?? 'free',
                (float) ($r['value'] ?? 0),
                (bool) ($r['multi_use'] ?? false),
                (int) ($r['max_uses'] ?? 1),
                (int) ($r['uses_count'] ?? 0),
                $expires,
                $r['date_mode'] ?? 'fixed',
                $r['created_by'] ?? '',
                $created,
                $r['status'] ?? 'aktiv',
                $payload,
            );
        }
        $this->saveAll($objects, true);
    }

    public function importArchive(array $data): void
    {
        $table = $this->config->get('storage_config')['vouchers_archive']['table'];
        $this->pdo->beginTransaction();

        try {
            $sql  = null;
            $stmt = null;

            foreach ($data as $id => $item) {
                $mapped = [
                    'id'          => $id,
                    'code'        => $item['code'] ?? '',
                    'redeemed_at' => $item['redeemed_at'] ?? '',
                    'user_name'   => $item['user_name'] ?? '',
                    'user_plot'   => $item['user_plot'] ?? '',
                ];

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $mapped);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($mapped);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
