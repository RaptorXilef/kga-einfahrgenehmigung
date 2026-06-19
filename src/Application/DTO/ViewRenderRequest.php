<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO für alle allgemeinen View-Render-Requests (GET-Parameter).
 *
 * Path: src/Application/DTO/ViewRenderRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ViewRenderRequest
{
    private function __construct(
        public string $message,
        public bool $isSuccess,
        public int $step,
        public array $queryData,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(
            \trim((string) ($get['msg'] ?? '')),
            ($get['sent'] ?? '') === '1',
            ($get['sent'] ?? '0') === '1' ? 2 : 1,
            $get,
        );
    }
}
