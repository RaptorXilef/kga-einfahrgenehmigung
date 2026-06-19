<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;

/**
 * Fängt CORS Pre-Flight Requests ab, bevor sie die Anwendung belasten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    public function process(array $requestData, callable $next): mixed
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            \http_response_code(204);
            exit; // Pre-flight erfolgreich abgefangen und beendet
        }

        return $next($requestData);
    }
}
