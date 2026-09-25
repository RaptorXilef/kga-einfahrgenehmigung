<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Override;
use PDO;

final readonly class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    #[Override]
    public function loadAll(): array
    {
        $users = [];
        $stmt = $this->pdo->query('SELECT * FROM users ORDER BY username ASC');

        if ($stmt !== false) {
            while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $users[(string) $row['id']] = $this->mapRowToEntity($row);
            }
        }

        return $users;
    }

    #[Override]
    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!\is_array($row)) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    #[Override]
    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!\is_array($row)) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    #[Override]
    public function save(User $user): void
    {
        $sql = 'INSERT INTO users (id, username, role_id, pass, last_seen_changelog)
                VALUES (:id, :username, :role, :pass, :changelog)
                ON DUPLICATE KEY UPDATE
                username = VALUES(username), role_id = VALUES(role_id),
                pass = VALUES(pass), last_seen_changelog = VALUES(last_seen_changelog)';

        $this->pdo->prepare($sql)->execute([
            'id' => $user->id,
            'username' => $user->getUsername(),
            'role' => $user->getRoleId(),
            'pass' => $user->getPasswordHash(),
            'changelog' => $user->getLastSeenChangelog(),
        ]);
    }

    #[Override]
    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    }

    private function mapRowToEntity(array $row): User
    {
        return new User(
            id: (string) $row['id'],
            username: (string) $row['username'],
            roleId: (string) ($row['role_id'] ?? $row['group'] ?? 'guest'),
            passwordHash: (string) ($row['pass'] ?? $row['password_hash'] ?? ''),
            lastSeenChangelog: (string) ($row['last_seen_changelog'] ?? 'v0.0.0'),
        );
    }
}
