<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\Group;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonGroupRepository implements GroupRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function loadAll(): array
    {
        $cfg  = $this->config->get('storage_config')['groups'];
        $path = $this->config->getStoragePath($cfg['file']);

        $groups = [];
        if (! \file_exists($path)) {
            return $groups;
        }

        $data = $this->jsonHelper->read($path);
        foreach ($data as $id => $row) {
            $groups[$id] = new Group(
                $id,
                $row['name'],
                $row['permissions'] ?? [],
            );
        }

        return $groups;
    }

    /**
     * @param Group[] $groups
     */
    public function saveAll(array $groups, bool $forceSql = false): void
    {
        if ($forceSql) {
            return;
        }

        $cfg        = $this->config->get('storage_config')['groups'];
        $dataToSave = [];

        foreach ($groups as $id => $group) {
            $dataToSave[$id] = [
                'name'        => $group->name,
                'permissions' => $group->permissions,
            ];
        }

        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $dataToSave);
    }

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $id => $row) {
            $objects[$id] = new Group((string) $id, $row['name'] ?? '', $row['permissions'] ?? []);
        }
        $this->saveAll($objects);
    }
}
