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
final readonly class MySqlGroupRepository implements GroupRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function loadAll(): array
    {
        $cfg    = $this->config->get('storage_config')['groups'];
        $groups = [];

        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $perms = \is_string($row['permissions'])
                ? $this->jsonHelper->decode($row['permissions'])
                : $row['permissions'];

            $groups[$row['id']] = new Group(
                $row['id'],
                $row['name'],
                $perms ?? [],
            );
        }

        return $groups;
    }

    /**
     * @param Group[] $groups
     */
    public function saveAll(array $groups, bool $forceSql = false): void
    {
        $table = $this->config->get('storage_config')['groups']['table'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");

            $sql  = null;
            $stmt = null;

            foreach ($groups as $id => $group) {
                $data = [
                    'id'          => $id,
                    'name'        => $group->name,
                    'permissions' => \json_encode($group->permissions, \JSON_UNESCAPED_UNICODE),
                ];

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $data);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($data);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
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
