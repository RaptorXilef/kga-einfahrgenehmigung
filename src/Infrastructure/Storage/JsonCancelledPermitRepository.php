<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Core\Entity\Permit;

final readonly class JsonCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use SafeJsonWriterTrait;
    use StorageMapperTrait;

    public function __construct(private ConfigInterface $config)
    {
    }

    public function saveCancelled(Permit $permit): void
    {
        $path                = $this->config->getStoragePath($this->config->get('storage_config')['permits_cancelled']['file']);
        $data                = \file_exists($path) ? JsonHelper::read($path) : [];
        $data[$permit->code] = $this->flattenEntity($permit);
        $this->writeJsonSafely($path, $data);
    }

    public function isCodeCancelled(string $code): bool
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['permits_cancelled']['file']);
        if (\file_exists($path)) {
            $data = JsonHelper::read($path);

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
        $data    = JsonHelper::read($path);
        $permits = \array_map($this->mapToEntity(...), $data);
        \usort($permits, fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $permits;
    }
}
