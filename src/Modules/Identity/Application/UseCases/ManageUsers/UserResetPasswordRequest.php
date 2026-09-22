<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;

final readonly class UserResetPasswordRequest
{
    private function __construct(
        public string $userId,
        public string $newPassword,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $userId = (string) ($post['user_id'] ?? '');
        $pw1 = (string) ($post['password'] ?? '');
        $pw2 = (string) ($post['password_repeat'] ?? '');

        if ($userId === '') {
            throw ValidationException::withMessage('Fehler: Keine Benutzer-ID übergeben.');
        }
        if ($pw1 !== $pw2) {
            throw ValidationException::withMessage('Fehler: Passwörter nicht identisch.');
        }
        if (\strlen($pw1) < 8) {
            throw ValidationException::withMessage('Fehler: Passwort muss mindestens 8 Zeichen lang sein.');
        }

        return new self($userId, $pw1);
    }
}
