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

        return JsonHelper::read($path);
    }

    public function saveAll(array $vouchers, bool $forceSql = false): void
    {
        if ($forceSql) {
            return;
        }
        $cfg  = $this->config->get('storage_config')['vouchers'];
        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $vouchers);
    }

    public function loadArchive(): array
    {
        $cfg  = $this->config->get('storage_config')['vouchers_archive'];
        $path = $this->config->getStoragePath($cfg['file']);

        return JsonHelper::read($path);
    }

    public function appendToArchive(array $archiveEntry): void
    {
        $arcCfg      = $this->config->get('storage_config')['vouchers_archive'];
        $archivePath = $this->config->getStoragePath($arcCfg['file']);
        $archive     = JsonHelper::read($archivePath);
        $archive[]   = $archiveEntry;
        $this->writeJsonSafely($archivePath, $archive);
    }
}
