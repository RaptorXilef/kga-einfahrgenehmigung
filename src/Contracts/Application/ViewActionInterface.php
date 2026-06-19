<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * Interface für Action-Klassen, die direkt Views/HTML rendern (Read-Only).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
