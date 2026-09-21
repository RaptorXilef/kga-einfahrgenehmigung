<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use PDO;

final readonly class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new User(
            id: (string) $row['id'],
            username: (string) $row['username'],
            roleId: (string) ($row['role_id'] ?? $row['group'] ?? 'guest'),
            passwordHash: (string) ($row['pass'] ?? $row['password_hash'] ?? ''),
            lastSeenChangelog: (string) ($row['last_seen_changelog'] ?? 'v0.0.0'),
        );
    }

    public function save(User $user): void
    {
        $sql = 'INSERT INTO users (id, username, role_id, pass, last_seen_changelog)
                VALUES (:id, :username, :role, :pass, :changelog)
                ON DUPLICATE KEY UPDATE
                username = VALUES(username), role_id = VALUES(role_id),
                pass = VALUES(pass), last_seen_changelog = VALUES(last_seen_changelog)';

        $this->pdo->prepare($sql)->execute([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->roleId,
            'pass' => $user->getPasswordHash(),
            'changelog' => $user->getLastSeenChangelog(),
        ]);
    }
}
