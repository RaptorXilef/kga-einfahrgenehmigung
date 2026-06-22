<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiCheckUpdateRequest
{
    private function __construct(
        public bool $force,
    ) {
    }

    public static function fromArray(array $input): self
    {
        $force = isset($input['force']) && $input['force'] === true;

        return new self($force);
    }
}
