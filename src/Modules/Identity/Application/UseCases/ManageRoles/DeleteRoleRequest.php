<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Exception\ValidationException;

final readonly class DeleteRoleRequest
{
    private function __construct(
        public string $roleId,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $roleId = \trim((string) ($post['group_id'] ?? ''));

        if ($roleId === '' || !\preg_match('/^[a-zA-Z0-9_\-]+$/', $roleId)) {
            throw ValidationException::withMessage('Fehler: Ungültige oder fehlende Rollen-ID.');
        }

        return new self($roleId);
    }
}
