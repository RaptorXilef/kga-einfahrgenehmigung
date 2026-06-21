<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['users'];
        $users = [];

        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $users[$row['id']] = new User(
                $row['id'],
                $row['username'],
                $row['group'],
                $row['pass'],
            );
        }

        return $users;
    }

    public function saveAll(array $users, bool $forceSql = false): void
    {
        // $forceSql wird hier ignoriert, da wir ohnehin in MySQL speichern.
        $cfg = $this->config->get('storage_config')['users'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (id, username, `group`, pass) VALUES (?, ?, ?, ?)");
            foreach ($users as $id => $user) {
                $stmt->execute([$id, $user->username, $user->groupId, $user->passwordHash]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
