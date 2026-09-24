<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use PDO;

final readonly class PdoRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function loadAll(): array
    {
        $roles = [];
        $stmt = $this->pdo->query('SELECT * FROM roles ORDER BY name ASC');

        // VSA FIX: Memory Safe Unbuffered Loop
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $roles[$row['id']] = $this->mapToEntity($row);
        }

        return $roles;
    }

    public function findById(string $id): ?Role
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapToEntity($row) : null;
    }

    public function save(Role $role): void
    {
        $sql = 'INSERT INTO roles (id, name, permissions) VALUES (:id, :name, :perms)
                ON DUPLICATE KEY UPDATE name = VALUES(name), permissions = VALUES(permissions)';
        $this->pdo->prepare($sql)->execute([
            'id' => $role->id,
            'name' => $role->name,
            'perms' => \json_encode($role->permissions, \JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $id]);
    }

    private function mapToEntity(array $row): Role
    {
        $perms = \is_string($row['permissions']) ? \json_decode($row['permissions'], true) : $row['permissions'];

        return new Role((string) $row['id'], (string) $row['name'], $perms ?? []);
    }
}
