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
final readonly class MySqlGroupRepository implements GroupRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg    = $this->config->get('storage_config')['groups'];
        $groups = [];

        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $perms = \is_string($row['permissions'])
                ? JsonHelper::decode($row['permissions'])
                : $row['permissions'];

            $groups[$row['id']] = new Group(
                $row['id'],
                $row['name'],
                $perms ?? [],
            );
        }

        return $groups;
    }

    public function saveAll(array $groups, bool $forceSql = false): void
    {
        $cfg = $this->config->get('storage_config')['groups'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (id, name, permissions) VALUES (?, ?, ?)");

            foreach ($groups as $id => $group) {
                $stmt->execute([
                    $id,
                    $group->name,
                    \json_encode($group->permissions, \JSON_UNESCAPED_UNICODE),
                ]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
