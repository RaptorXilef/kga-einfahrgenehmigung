<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Permit-Modul (Bounded Context Kommunikation).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface PermitIntegrationInterface
{
    public function hasPermits(string $email): bool;
}
