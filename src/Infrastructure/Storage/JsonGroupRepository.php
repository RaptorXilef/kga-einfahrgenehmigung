<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
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

        $data = JsonHelper::read($path);
        foreach ($data as $id => $row) {
            $groups[$id] = new Group(
                $id,
                $row['name'],
                $row['permissions'] ?? [],
            );
        }

        return $groups;
    }

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
}
