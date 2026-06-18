<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Event: Wird geworfen, wenn ein Nutzer einen Login-Link für seine Historie anfordert.
 *
 * Path: src/Core/Event/MagicLinkRequestedEvent.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MagicLinkRequestedEvent
{
    public function __construct(
        public string $email,
        public string $token,
        public string $code,
    ) {
    }
}
