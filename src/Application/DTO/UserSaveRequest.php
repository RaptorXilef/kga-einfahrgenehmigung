<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * Data Transfer Object (DTO) für das Erstellen eines neuen Benutzers.
 * Kapselt die Validierung, Typisierung und die hochgeladene Avatar-Datei.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserSaveRequest
{
    private function __construct(
        public string $username,
        public string $password,
        public string $group,
        public ?array $avatar, // Gekapseltes Datei-Array
    ) {
    }

    /**
     * Named Constructor (Factory Method): Baut das DTO aus dem rohen POST-Array.
     * Führt zwingend alle Validierungen durch!
     *
     * @param  array<string, mixed> $post Das rohe $_POST Array.
     * @throws ValidationException  Wenn die Daten ungültig sind.
     */
    public static function fromArray(array $post, array $files = []): self
    {
        $username = \trim((string) ($post['username'] ?? ''));
        $pw1      = (string) ($post['password'] ?? '');
        $pw2      = (string) ($post['password_repeat'] ?? '');
        $group    = (string) ($post['group'] ?? 'guest');

        // 1. Validierung: Pflichtfelder
        if ($username === '') {
            throw ValidationException::withMessage('Fehler: Der Benutzername darf nicht leer sein.');
        }

        // 2. Validierung: Passwörter
        if ($pw1 === '' || $pw1 === '0') {
            throw ValidationException::withMessage('Fehler: Das Passwort darf nicht leer sein.');
        }
        if ($pw1 !== $pw2) {
            throw ValidationException::withMessage('Fehler: Die Passwörter stimmen nicht überein.');
        }
        if (\strlen($pw1) < 8) {
            throw ValidationException::withMessage('Fehler: Das Passwort muss mindestens 8 Zeichen lang sein.');
        }

        $avatarFile      = $files['avatar'] ?? null;
        $validatedAvatar = null;
        if ($avatarFile && isset($avatarFile['error']) && $avatarFile['error'] === 0) {
            $validatedAvatar = $avatarFile;
        }

        return new self(
            $username,
            $pw1,
            $group,
            $validatedAvatar,
        );
    }
}
