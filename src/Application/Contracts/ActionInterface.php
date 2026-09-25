<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Application\Http\ServerRequest;

/**
 * Interface für alle ausführbaren Action-Klassen (Single Action Controller).
 */
interface ActionInterface
{
    /**
     * Führt die definierte Aktion aus.
     */
    public function execute(ServerRequest $request): ResponseInterface;
}
