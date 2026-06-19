<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für die Übermittlung des Verifizierungscodes inklusive IP-Kapselung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
