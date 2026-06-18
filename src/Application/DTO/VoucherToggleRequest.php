<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Aktivieren/Deaktivieren eines Gutscheins.
 * Übersetzt die Action-Direktive direkt in den Ziel-Status.
 *
 * Path: src/Application/DTO/VoucherToggleRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VoucherToggleRequest
{
    private function __construct(
        public string $code,
        public string $targetStatus,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $code = \trim((string) ($post['code'] ?? ''));

        if ($code === '') {
            throw ValidationException::withMessage('Fehler: Fehlender Parameter (code).');
        }

        $targetStatus = ($post['action'] ?? '') === 'activate_voucher' ? 'aktiv' : 'deaktiviert';

        return new self($code, $targetStatus);
    }
}
