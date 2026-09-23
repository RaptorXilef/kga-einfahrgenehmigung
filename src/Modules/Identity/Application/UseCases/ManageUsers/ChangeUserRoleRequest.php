<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;
use App\SharedKernel\Application\Security\Sanitizer;

final readonly class ChangeUserRoleRequest
{
    private function __construct(
        public string $userId,
        public string $roleId,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $userId = Sanitizer::string($post['user_id'] ?? '');
        $roleId = Sanitizer::string($post['group'] ?? '');

        if ($userId === '') {
            throw ValidationException::withMessage('Fehler: Kein Benutzer ausgewählt.');
        }

        if ($roleId === '') {
            throw ValidationException::withMessage('Fehler: Keine Rolle ausgewählt.');
        }

        return new self($userId, $roleId);
    }
}
