<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/DTO/SystemMaintenanceRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemMaintenanceRequest
{
    private function __construct(
        public string $target,
        public string $direction,
        public string $timestamp,
        public string $engine,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        return new self(
            \trim((string) ($post['target'] ?? '')),
            \trim((string) ($post['direction'] ?? 'sync')),
            \trim((string) ($post['timestamp'] ?? '')),
            \trim((string) ($post['engine'] ?? 'all')),
        );
    }
}
