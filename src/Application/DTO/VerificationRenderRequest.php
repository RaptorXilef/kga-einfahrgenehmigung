<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VerificationRenderRequest
{
    public function __construct(
        public bool $isError,
        public string $message,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(isset($get['error']), \trim((string) ($get['msg'] ?? '')));
    }
}
