<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO für den asynchronen PayPal-Webhook/API-Call.
 * Kapselt das Lesen aus dem php://input Stream.
 *
 * Path: src/Application/DTO/ApiDateInfoRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ApiDateInfoRequest
{
    private function __construct(public \DateTimeImmutable $von, public \DateTimeImmutable $bis)
    {
    }

    public static function fromArray(array $input): self
    {
        $vonStr = (string) ($input['von'] ?? 'today');
        $bisStr = (string) ($input['bis'] ?? 'today');

        try {
            $von = new \DateTimeImmutable($vonStr);
        } catch (\Exception) {
            $von = new \DateTimeImmutable('today');
        }

        try {
            $bis = new \DateTimeImmutable($bisStr);
        } catch (\Exception) {
            $bis = new \DateTimeImmutable('today');
        }

        return new self($von, $bis);
    }
}
