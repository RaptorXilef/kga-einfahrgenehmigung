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

    public static function fromGlobalStream(): self
    {
        $input = \file_get_contents('php://input');

        try {
            $data = \json_decode((string) $input, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessage('Ungültiges JSON-Format gesendet.');
        }

        $orderId = (string) ($data['orderID'] ?? '');
        $token   = (string) ($data['token'] ?? '');

        if ($orderId === '' || $token === '') {
            throw ValidationException::withMessage('Fehlende Parameter (orderID oder token).');
        }

        return new self($orderId, $token);
    }
}
