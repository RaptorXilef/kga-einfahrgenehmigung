<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\Role;
use Exception;
use PDO;

final readonly class MySqlRoleRepository implements RoleRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private PDO $pdo,
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

        $roles = [];
        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}` ORDER BY name ASC");
        if ($stmt === false) {
            return [];
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $perms = \is_string($row['permissions'])
                ? $this->jsonHelper->decode($row['permissions'])
                : $row['permissions'];

            $roles[$row['id']] = new Role(
                $row['id'],
                $row['name'],
                $perms ?? [],
            );
        }

        return $roles;
    }

    public function saveAll(array $roles, bool $forceSql = false): void
    {
        $table = $this->config->get('storage_config')['roles']['table'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");

            $sql = null;
            $stmt = null;

            foreach ($roles as $id => $role) {
                $data = [
                    'id' => $id,
                    'name' => $role->name,
                    'permissions' => \json_encode($role->permissions, \JSON_UNESCAPED_UNICODE),
                ];

                if ($sql === null) {
                    $sql = $this->buildReplaceSql($table, $data);
                    $stmt = $this->pdo->prepare($sql);
                }

                $stmt->execute($data);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $id => $row) {
            $objects[$id] = new Role(
                (string) $id,
                $row['name'] ?? '',
                $row['permissions'] ?? [],
            );
        }
        $this->saveAll($objects, true);
    }
}
