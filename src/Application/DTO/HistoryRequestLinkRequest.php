<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für die Anforderung eines Magic-Links inklusive IP-Kapselung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryRequestLinkRequest
{
    private function __construct(
        public string $email,
        public string $ip,
    ) {
    }

    public static function fromRequestData(array $requestData): self
    {
        $post  = $requestData['post'] ?? [];
        $email = \trim((string) ($post['email'] ?? ''));

        if ($email === '' || ! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }

        $ip = (string) ($requestData['ip'] ?? 'unknown');

        return new self($email, $ip);
    }
}
