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
final readonly class JsonVoucherRepository implements VoucherRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(private ConfigInterface $config)
    {
    }

    public function loadAll(): array
    {
        $cfg  = $this->config->get('storage_config')['vouchers'];
        $path = $this->config->getStoragePath($cfg['file']);
        $raw  = \file_exists($path) ? JsonHelper::read($path) : [];

        $vouchers = [];
        foreach ($raw as $code => $r) {
            $expires = ! empty($r['expires_at']) ? new \DateTimeImmutable($r['expires_at']) : null;
            $created = ! empty($r['created_at']) ? new \DateTimeImmutable($r['created_at']) : new \DateTimeImmutable();

            $vouchers[$code] = new Voucher(
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
                $r['data'] ?? [],
            );
        }

        return $vouchers;
    }

    public function saveAll(array $vouchers, bool $forceSql = false): void
    {
        if ($forceSql) {
            return;
        }
        $cfg = $this->config->get('storage_config')['vouchers'];

        $dataToSave = [];
        foreach ($vouchers as $code => $v) {
            $dataToSave[$code] = [
                'code'       => $v->code, 'reason' => $v->reason, 'template_key' => $v->templateKey,
                'type'       => $v->type, 'value' => $v->value, 'multi_use' => $v->multiUse,
                'max_uses'   => $v->maxUses, 'uses_count' => $v->usesCount,
                'expires_at' => $v->expiresAt?->format('Y-m-d H:i:s'), 'date_mode' => $v->dateMode,
                'created_by' => $v->createdBy, 'created_at' => $v->createdAt->format('Y-m-d H:i:s'),
                'status'     => $v->status, 'data' => $v->data,
            ];
        }
        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $dataToSave);
    }

    public function loadArchive(): array
    {
        $cfg  = $this->config->get('storage_config')['vouchers_archive'];
        $path = $this->config->getStoragePath($cfg['file']);

        return \file_exists($path) ? JsonHelper::read($path) : [];
    }

    public function appendToArchive(array $archiveEntry): void
    {
        $arcCfg      = $this->config->get('storage_config')['vouchers_archive'];
        $archivePath = $this->config->getStoragePath($arcCfg['file']);
        $archive     = \file_exists($archivePath) ? JsonHelper::read($archivePath) : [];
        $archive[]   = $archiveEntry;
        $this->writeJsonSafely($archivePath, $archive);
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
        $this->saveAll($objects);
    }

    public function importArchive(array $data): void
    {
        $cfg  = $this->config->get('storage_config')['vouchers_archive'];
        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, \array_values($data));
    }
}
