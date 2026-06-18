<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für die Übermittlung des Verifizierungscodes inklusive IP-Kapselung.
 *
 * Path: src/Application/DTO/VerificationSubmitRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationSubmitRequest
{
    private function __construct(
        public string $token,
        public string $ip,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromRequestData(array $requestData): self
    {
        // Prüft, ob es per GET (Link) oder POST (Formular) kam
        $token = isset($requestData['get']['token'])
            ? (string) $requestData['get']['token']
            : \trim((string) ($requestData['post']['verification_code'] ?? ''));

        if ($token === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Verifizierungscode ein.');
        }

        $ip = (string) ($requestData['ip'] ?? 'unknown');

        return new self($token, $ip);
    }
}
