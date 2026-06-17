<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für die Anforderung eines Magic-Links im History-Portal.
 *
 * Path: src/Application/DTO/HistoryRequestLinkRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryRequestLinkRequest
{
    private function __construct(
        public string $email,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $email = \trim((string) ($post['email'] ?? ''));

        if ($email === '' || ! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }

        return new self($email);
    }
}
