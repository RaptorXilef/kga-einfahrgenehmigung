<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Event: Wird geworfen, wenn ein Nutzer das Antragsformular absendet und seine Mail verifizieren muss.
 *
 * Path: src/Core/Event/VerificationRequestedEvent.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationRequestedEvent
{
    public function __construct(
        public array $data,
        public string $token,
        public string $shortCode,
    ) {
    }
}
