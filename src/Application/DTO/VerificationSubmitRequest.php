<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;

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

    public static function fromRequest(ServerRequest $request): self
    {
        $token = isset($request->get['token'])
            ? (string) $request->get['token']
            : \trim((string) ($request->post['verification_code'] ?? ''));

        if ($token === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Verifizierungscode ein.');
        }
        $ip = $request->getIp();

        return new self($token, $ip);
    }
}
