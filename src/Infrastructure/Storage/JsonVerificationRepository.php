<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonVerificationRepository implements VerificationRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function loadPending(): array
    {
        $data   = $this->loadJson('pending_verification');
        $nowStr = APP_REQUEST_TIME_STR;

        return \array_filter($data, fn (array $item): bool => isset($item['expires']) && $item['expires'] > $nowStr);
    }

    public function savePending(array $data, bool $forceSql = false): void
    {
        if (! $forceSql) {
            $this->saveJson('pending_verification', $data);
        }
    }

    public function loadVerified(): array
    {
        return $this->loadJson('verified_pending');
    }

    public function saveVerified(array $data, bool $forceSql = false): void
    {
        if (! $forceSql) {
            $this->saveJson('verified_pending', $data);
        }
    }

    private function loadJson(string $targetKey): array
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $path = $this->config->getStoragePath($cfg['file']);
        $data = \file_exists($path) ? JsonHelper::read($path) : [];
        foreach ($data as &$item) {
            if (isset($item['expires']) && \is_numeric($item['expires'])) {
                $item['expires'] = \date('Y-m-d H:i:s', (int) $item['expires']);
            }
        }

        return $data;
    }

    private function saveJson(string $targetKey, array $data): void
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $data);
    }
}
