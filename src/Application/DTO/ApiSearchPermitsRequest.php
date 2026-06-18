<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO für den asynchronen PayPal-Webhook/API-Call.
 * Kapselt das Lesen aus dem php://input Stream.
 *
 * Path: src/Application/DTO/ApiSearchPermitsRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ApiSearchPermitsRequest
{
    private function __construct(
        public string $query,
        public int $page,
        public int $limit,
        public string $tab,
        public string $template,
    ) {
    }

    public static function fromArray(array $post): self
    {
        return new self(
            \trim((string) ($post['q'] ?? '')),
            \max(1, (int) ($post['page'] ?? 1)),
            \max(10, \min(100, (int) ($post['limit'] ?? 50))),
            (string) ($post['tab'] ?? 'all'),
            (string) ($post['template'] ?? 'all'),
        );
    }
}
