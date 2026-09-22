<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;

final readonly class UserRenameRequest
{
    private function __construct(
        public string $userId,
        public string $newUsername,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $userId = (string) ($post['user_id'] ?? '');
        $newName = \trim((string) ($post['new_username'] ?? ''));

        if ($userId === '') {
            throw ValidationException::withMessage('Fehler: Kein Benutzer ausgewählt.');
        }

        if (\str_starts_with(\strtolower($newName), 'sys_')) {
            throw ValidationException::withMessage('Fehler: Namen mit dem Präfix "sys_" sind für das System reserviert.');
        }

        if ($newName === '') {
            throw ValidationException::withMessage('Fehler: Der neue Login-Name darf nicht leer sein.');
        }

        return new self($userId, $newName);
    }
}
