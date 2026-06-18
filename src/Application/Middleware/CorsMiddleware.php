<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;

/**
 * Fängt CORS Pre-Flight Requests ab, bevor sie die Anwendung belasten.
 *
 * Path: src/Application/Middleware/CorsMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
