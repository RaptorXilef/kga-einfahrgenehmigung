<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO für den asynchronen PayPal-Webhook/API-Call.
 * Kapselt das Lesen aus dem php://input Stream.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
