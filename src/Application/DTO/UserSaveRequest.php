<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * Data Transfer Object (DTO) für das Erstellen eines neuen Benutzers.
 * Kapselt die Validierung und Typisierung der Formulardaten.
 *
 * Path: src/Application/DTO/UserSaveRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserSaveRequest
{
    private function __construct(
        public string $username,
        public string $password,
        public string $group,
    ) {
    }

    /**
     * Named Constructor (Factory Method): Baut das DTO aus dem rohen POST-Array.
     * Führt zwingend alle Validierungen durch!
     *
     * @param  array<string, mixed> $post Das rohe $_POST Array.
     * @throws ValidationException  Wenn die Daten ungültig sind.
     */
    public static function fromArray(array $post): self
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

        // Wenn wir hier ankommen, sind die Daten zu 100% sauber und sicher!
        return new self($username, $pw1, $group);
    }
}
