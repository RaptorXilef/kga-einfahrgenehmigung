<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use Exception;
use PDO;

final readonly class MySqlUserRepository implements UserRepositoryInterface
{
    use DynamicSqlTrait;
    use EntityHydratorTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg = $this->config->get('storage_config')['users'];
        $users = [];

        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Mapping nutzt nun primär 'role_id' mit Fallback auf 'group' falls die Migration noch nicht lief
            $users[$row['id']] = $this->hydrateEntity(User::class, $row, [
                'roleId' => $row['role_id'] ?? $row['group'] ?? 'guest',
                'passwordHash' => $row['pass'] ?? $row['password_hash'] ?? '',
            ]);
        }

        return $users;
    }

    /**
     * @param User[] $users
     */
    public function saveAll(array $users, bool $forceSql = false): void
    {
        $table = $this->config->get('storage_config')['users']['table'];

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");
            $sql = null;
            $stmt = null;

            foreach ($users as $id => $user) {
                // Verwendet ab sofort die saubere und korrekte Spalte 'role_id'
                $data = [
                    'id' => $id,
                    'username' => $user->username,
                    'role_id' => $user->roleId,
                    'pass' => $user->passwordHash,
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
}
