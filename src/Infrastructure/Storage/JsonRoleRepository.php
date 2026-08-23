<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\Role;

final readonly class JsonRoleRepository implements RoleRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function loadAll(): array
    {
        $cfg = $this->config->get('storage_config')['roles'] ?? null;
        if (!$cfg) {
            return [];
        }

        $path = $this->config->getStoragePath($cfg['file']);
        $roles = [];

        if (!\file_exists($path)) {
            return $roles;
        }

        $data = $this->jsonHelper->read($path);
        foreach ($data as $id => $row) {
            $roles[$id] = new Role(
                (string) $id,
                $row['name'] ?? '',
                $row['permissions'] ?? [],
            );
        }

        return $roles;
    }

    public function saveAll(array $roles, bool $forceSql = false): void
    {
        if ($forceSql) {
            return;
        }

        $cfg = $this->config->get('storage_config')['roles'];
        $dataToSave = [];

        foreach ($roles as $id => $role) {
            $dataToSave[$id] = [
                'name' => $role->name,
                'permissions' => $role->permissions,
            ];
        }

        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $dataToSave);
    }
}
