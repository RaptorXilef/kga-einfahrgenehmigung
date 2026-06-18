<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für den asynchronen PayPal-Webhook/API-Call.
 * Kapselt das Lesen aus dem php://input Stream.
 *
 * Path: src/Application/DTO/CapturePaymentRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CapturePaymentRequest
{
    private function __construct(
        public string $orderId,
        public string $token,
    ) {
    }

    public static function fromArray(array $input): self
    {
        $orderId = (string) ($input['orderID'] ?? '');
        $token   = (string) ($input['token'] ?? '');

        if ($orderId === '' || $token === '') {
            throw ValidationException::withMessage('Fehlende Parameter (orderID oder token).');
        }

        return new self($orderId, $token);
    }
}
