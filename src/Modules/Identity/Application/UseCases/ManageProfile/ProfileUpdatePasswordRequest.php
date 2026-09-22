<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Exception\ValidationException;

final readonly class ProfileUpdatePasswordRequest
{
    private function __construct(
        public string $oldPassword,
        public string $newPassword,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $oldPass = (string) ($post['old_password'] ?? '');
        $newPass = (string) ($post['new_password'] ?? '');
        $confirm = (string) ($post['confirm_password'] ?? '');

        if ($oldPass === '') {
            throw ValidationException::withMessage('Fehler: Bitte geben Sie Ihr aktuelles Passwort ein.');
        }
        if ($newPass !== $confirm) {
            throw ValidationException::withMessage('Fehler: Die Passwort-Bestätigung stimmt nicht überein.');
        }
        if (\strlen($newPass) < 8) {
            throw ValidationException::withMessage('Fehler: Das neue Passwort muss mindestens 8 Zeichen lang sein.');
        }

        return new self($oldPass, $newPass);
    }
}
