<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\Permit;

final readonly class JsonCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use SafeJsonWriterTrait;
    use StorageMapperTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function saveCancelled(Permit $permit): void
    {
        $path                       = $this->config->getStoragePath($this->config->get('storage_config')['permits_cancelled']['file']);
        $data                       = \file_exists($path) ? $this->jsonHelper->read($path) : [];
        $data[$permit->code->value] = $this->flattenEntity($permit);
        $this->writeJsonSafely($path, $data);
    }

    public function isCodeCancelled(string $code): bool
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['permits_cancelled']['file']);
        if (\file_exists($path)) {
            $data = $this->jsonHelper->read($path);

            return isset($data[$code]);
        }

        return false;
    }

    public function loadAll(): array
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['permits_cancelled']['file']);
        if (! \file_exists($path)) {
            return [];
        }
        $data    = $this->jsonHelper->read($path);
        $permits = \array_map($this->mapToEntity(...), $data);
        \usort($permits, fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $permits;
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

        $path       = $this->config->getStoragePath($this->config->get('storage_config')['permits_cancelled']['file']);
        $mappedData = [];
        foreach ($objects as $permit) {
            $item                  = $this->flattenEntity($permit);
            $item['is_anonymized'] = 1;
            $item['agreements'] ??= '{}';
            $mappedData[$permit->code->value] = $item;
        }
        $this->writeJsonSafely($path, $mappedData);
    }
}
