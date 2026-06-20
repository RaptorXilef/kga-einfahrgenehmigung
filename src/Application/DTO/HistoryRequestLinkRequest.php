<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;

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

    public static function fromRequest(ServerRequest $request): self
    {
        $post  = $request->post;
        $email = \trim((string) ($post['email'] ?? ''));
        if ($email === '' || ! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }
        $ip = $request->getIp();

        return new self($email, $ip);
    }
}
