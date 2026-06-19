<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Event: Wird geworfen, wenn ein Nutzer einen Login-Link für seine Historie anfordert.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
