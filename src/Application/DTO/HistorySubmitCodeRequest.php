<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

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

    public static function fromRequestData(array $requestData): self
    {
        $post = $requestData['post'] ?? [];
        $code = \trim((string) ($post['login_code'] ?? ''));

        if ($code === '') {
            throw ValidationException::withMessage('Bitte geben Sie den 6-stelligen Code ein.');
        }

        $ip = (string) ($requestData['ip'] ?? 'unknown');

        return new self($code, $ip);
    }
}
