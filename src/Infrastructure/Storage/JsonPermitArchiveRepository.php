<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonPermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    use SafeJsonWriterTrait;
    use StorageMapperTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function isCodeInArchive(string $code): bool
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['permits_archive']['file']);
        if (\file_exists($path)) {
            $archiveData = $this->jsonHelper->read($path);

            return isset($archiveData[$code]);
        }

        return false;
    }

    public function archivePermits(int $year, array $permitsToArchive): void
    {
        if (empty($permitsToArchive)) {
            return;
        }
        $path     = $this->config->getStoragePath($this->config->get('storage_config')['permits_archive']['file']);
        $existing = \file_exists($path) ? $this->jsonHelper->read($path) : [];
        foreach ($permitsToArchive as $permit) {
            $existing[$permit->code] = $this->flattenEntity($permit);
        }
        $this->writeJsonSafely($path, $existing);
    }

    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['permits_archive']['file']);
        if (! \file_exists($path)) {
            return 0;
        }

        $cutoffDate      = \date('Y-m-d H:i:s', \strtotime("-{$yearsThreshold} years", APP_REQUEST_TIME));
        $anonymizedCount = 0;
        $existing        = $this->jsonHelper->read($path);
        $changed         = false;

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
            $this->writeJsonSafely($path, $existing);
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

    public function getArchivedPermits(int $minYear): array
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['permits_archive']['file']);
        if (! \file_exists($path)) {
            return [];
        }

        $rawArc  = $this->jsonHelper->read($path);
        $results = [];

        foreach ($rawArc as $aData) {
            $pYear = (int) \substr((string) ($aData['erstellt'] ?? $aData['von'] ?? '2000'), 0, 4);
            if ($pYear >= $minYear) {
                $results[] = $this->mapToEntity($aData);
            }
        }

        return $results;
    }
}
