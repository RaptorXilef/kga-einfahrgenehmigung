<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Application\Http\ServerRequest;

/**
 * Interface für Action-Klassen, die direkt Views/HTML rendern (Read-Only).
 */
interface ViewActionInterface
{
    /**
     * Führt die View-Aktion aus und rendert das Ergebnis.
     *
     * @return ResponseInterface
     */
    public function execute(ServerRequest $request): mixed;
}
