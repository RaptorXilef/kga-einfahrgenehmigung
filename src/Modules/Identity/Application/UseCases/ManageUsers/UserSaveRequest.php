<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;

final readonly class UserSaveRequest
{
    private function __construct(
        public string $username,
        public string $password,
        public string $group,
        public ?array $avatar,
    ) {
    }

    public static function fromArray(array $post, array $files = []): self
    {
        $username = \trim((string) ($post['username'] ?? ''));
        $pw1 = (string) ($post['password'] ?? '');
        $pw2 = (string) ($post['password_repeat'] ?? '');
        $group = (string) ($post['group'] ?? 'guest');

        if ($username === '') {
            throw ValidationException::withMessage('Fehler: Der Benutzername darf nicht leer sein.');
        }
        if (\str_starts_with(\strtolower($username), 'sys_')) {
            throw ValidationException::withMessage('Fehler: Namen mit dem Präfix "sys_" sind für das System reserviert.');
        }
        if ($pw1 === '' || $pw1 === '0') {
            throw ValidationException::withMessage('Fehler: Das Passwort darf nicht leer sein.');
        }
        if ($pw1 !== $pw2) {
            throw ValidationException::withMessage('Fehler: Die Passwörter stimmen nicht überein.');
        }
        if (\strlen($pw1) < 8) {
            throw ValidationException::withMessage('Fehler: Das Passwort muss mindestens 8 Zeichen lang sein.');
        }

        $avatarFile = $files['avatar'] ?? null;
        $validatedAvatar = null;
        if ($avatarFile && isset($avatarFile['error']) && $avatarFile['error'] === 0) {
            $validatedAvatar = $avatarFile;
        }

        return new self($username, $pw1, $group, $validatedAvatar);
    }
}
