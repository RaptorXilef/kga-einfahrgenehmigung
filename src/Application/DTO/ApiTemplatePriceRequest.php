<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO für den asynchronen PayPal-Webhook/API-Call.
 * Kapselt das Lesen aus dem php://input Stream.
 *
 * Path: src/Application/DTO/ApiTemplatePriceRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ApiTemplatePriceRequest
{
    private function __construct(public string $key, public string $typ, public string $voucherCode)
    {
    }

    public static function fromArray(array $input, string $defaultTyp): self
    {
        return new self(
            (string) ($input['key'] ?? 'std_7'),
            (string) ($input['typ'] ?? $defaultTyp),
            \strtoupper(\trim((string) ($input['voucher'] ?? ''))),
        );
    }
}
