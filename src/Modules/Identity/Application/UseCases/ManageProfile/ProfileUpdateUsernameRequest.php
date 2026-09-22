<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Exception\ValidationException;

final readonly class ProfileUpdateUsernameRequest
{
    private function __construct(public string $newUsername)
    {
    }

    public static function fromArray(array $post): self
    {
        $newName = \trim((string) ($post['new_username'] ?? ''));

        if ($newName === '') {
            throw ValidationException::withMessage('Fehler: Name darf nicht leer sein.');
        }

        return new self($newName);
    }
}
