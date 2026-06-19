<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * Interface für alle HTTP-Middlewares (Türsteher).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface MiddlewareInterface
{
    /**
     * Verarbeitet den Request und entscheidet, ob er an die nächste Schicht weitergegeben wird.
     *
     * @param array<string, mixed> $requestData Die gesammelten Request-Daten (GET, POST, etc.).
     * @param callable             $next        Die nächste Middleware oder die finale Action.
     */
    public function process(array $requestData, callable $next): mixed;
}
