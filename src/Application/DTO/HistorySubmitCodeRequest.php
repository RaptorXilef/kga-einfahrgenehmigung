<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;

/**
 * DTO für die Code-Eingabe im History-Portal inklusive IP-Kapselung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistorySubmitCodeRequest
{
    private function __construct(
        public string $loginCode,
        public string $ip,
    ) {
    }

    public static function fromRequest(ServerRequest $request): self
    {
        $post = $request->post;
        $code = \trim((string) ($post['login_code'] ?? ''));
        if ($code === '') {
            throw ValidationException::withMessage('Bitte geben Sie den 6-stelligen Code ein.');
        }
        $ip = $request->getIp();

        return new self($code, $ip);
    }
}
