<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;

final readonly class DeleteUserRequest
{
    private function __construct(
        public string $userId,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $userId = \trim((string) ($post['user_id'] ?? ''));

        if ($userId === '' || !\preg_match('/^[a-zA-Z0-9_\-]+$/', $userId)) {
            throw ValidationException::withMessage('Fehler: Ungültige oder fehlende Benutzer-ID.');
        }

        return new self($userId);
    }
}
