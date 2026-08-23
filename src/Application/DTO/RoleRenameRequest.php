<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Core\Security\Sanitizer;

final readonly class RoleRenameRequest
{
    private function __construct(
        public string $roleId,
        public string $newRoleName,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $roleId = Sanitizer::string($post['group_id'] ?? '');
        $newName = Sanitizer::string($post['new_group_name'] ?? '');

        if ($roleId === '') {
            throw ValidationException::withMessage('Fehler: Keine Rolle ausgewählt.');
        }
        if ($newName === '') {
            throw ValidationException::withMessage('Fehler: Der neue Rollenname darf nicht leer sein.');
        }

        return new self($roleId, $newName);
    }
}
