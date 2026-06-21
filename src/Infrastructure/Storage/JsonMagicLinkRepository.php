<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonMagicLinkRepository implements MagicLinkRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['magic_links'];
        $path  = $this->config->getStoragePath($cfg['file']);
        $links = \file_exists($path) ? JsonHelper::read($path) : [];
        foreach ($links as &$l) {
            if (isset($l['expires']) && \is_numeric($l['expires'])) {
                $l['expires'] = \date('Y-m-d H:i:s', (int) $l['expires']);
            }
        }

        return $links;
    }

    public function saveAll(array $links, bool $forceSql = false): void
    {
        if ($forceSql) {
            return;
        }
        $cfg  = $this->config->get('storage_config')['magic_links'];
        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $links, \JSON_PRETTY_PRINT);
    }
}
