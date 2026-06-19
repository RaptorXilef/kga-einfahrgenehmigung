<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Event: Wird geworfen, wenn ein Nutzer das Antragsformular absendet und seine Mail verifizieren muss.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
