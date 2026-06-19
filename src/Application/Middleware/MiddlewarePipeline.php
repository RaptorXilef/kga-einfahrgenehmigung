<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;

/**
 * Reiht Middlewares aneinander und führt sie sequenziell aus.
 *
 * Path: src/Application/Middleware/MiddlewarePipeline.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class MiddlewarePipeline
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middlewares = [];

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Schickt den Request durch alle Middlewares bis zur Kern-Aktion.
     */
    public function process(array $requestData, callable $coreAction): mixed
    {
        $next = $coreAction;

        // Wir bauen die Pipeline von hinten nach vorne auf (Onion-Architektur)
        for ($i = \count($this->middlewares) - 1; $i >= 0; --$i) {
            $middleware = $this->middlewares[$i];

            $next = function (array $req) use ($middleware, $next): mixed {
                return $middleware->process($req, $next);
            };
        }

        // Startschuss: Der Request betritt die äußerste Schicht
        return $next($requestData);
    }
}
