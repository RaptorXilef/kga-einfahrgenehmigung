<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * Interface für Action-Klassen, die direkt Views/HTML rendern (Read-Only).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface ViewActionInterface
{
    /**
     * Führt die View-Aktion aus und rendert das Ergebnis.
     *
     * @param array<string, mixed> $requestData GET- oder POST-Daten.
     */
    public function execute(array $requestData): mixed;
}
