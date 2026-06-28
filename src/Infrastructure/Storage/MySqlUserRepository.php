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
    use DynamicSqlTrait;

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

    /**
     * @param User[] $users
     */
    public function saveAll(array $users, bool $forceSql = false): void
    {
        // $forceSql wird hier ignoriert, da wir ohnehin in MySQL speichern.
        $table = $this->config->get('storage_config')['users']['table'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");

            $sql  = null;
            $stmt = null;

            foreach ($users as $id => $user) {
                $data = [
                    'id'       => $id,
                    'username' => $user->username,
                    'group'    => $user->groupId,
                    'pass'     => $user->passwordHash,
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
            $objects[$id] = new User((string) $id, $row['username'] ?? '', $row['group'] ?? 'guest', $row['pass'] ?? '');
        }
        $this->saveAll($objects);
    }
}
