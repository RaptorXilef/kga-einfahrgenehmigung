<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Core\Security\Sanitizer;

final readonly class RoleSaveRequest
{
    private function __construct(
        public string $roleId,
        public string $roleName,
        public string $inheritRole,
        public array $permissions,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $roleId = Sanitizer::string($post['group_id'] ?? ''); // UI sendet noch group_id
        $roleName = Sanitizer::string($post['group_name'] ?? '');
        $inherit = Sanitizer::string($post['inherit_group'] ?? '');
        $perms = (array) ($post['perms'] ?? []);

        if ($roleName === '') {
            throw ValidationException::withMessage('Fehler: Der Rollenname darf nicht leer sein.');
        }

        return new self($roleId, $roleName, $inherit, $perms);
    }
}
