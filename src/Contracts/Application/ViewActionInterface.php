<?php

declare(strict_types=1);

namespace App\Contracts\Application;

use App\Application\Http\ServerRequest;

/**
 * Interface für Action-Klassen, die direkt Views/HTML rendern (Read-Only).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface ViewActionInterface
{
    /**
     * Führt die View-Aktion aus und rendert das Ergebnis.
     */
    public function execute(\App\Application\Http\ServerRequest $request): mixed;
}
