<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für den asynchronen PayPal-Webhook/API-Call.
 * Kapselt das Lesen aus dem php://input Stream.
 *
 * Path: src/Application/DTO/ApiPerformUpdateRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ApiPerformUpdateRequest
{
    private function __construct(public string $zipUrl)
    {
    }

    public static function fromArray(array $input): self
    {
        $zipUrl = (string) ($input['zip_url'] ?? '');

        if ($zipUrl === '') {
            throw ValidationException::withMessage('Keine Download-URL übergeben.');
        }

        // TODO URL GITHUB
        $allowedPrefix = 'https://github.com/RaptorXilef/kga-einfahrgenehmigung/releases/download/';
        if (! \str_starts_with($zipUrl, $allowedPrefix)) {
            throw ValidationException::withMessage('Sicherheitsverletzung: Ungültige Update-Quelle (SSRF Block).');
        }

        return new self($zipUrl);
    }
}
