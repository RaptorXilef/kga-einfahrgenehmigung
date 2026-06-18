<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;

/**
 * Middleware zur Validierung der zulässigen HTTP-Methoden.
 *
 * Path: src/Application/Middleware/HttpMethodMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HttpMethodMiddleware implements MiddlewareInterface
{
    public function __construct(private array $allowedMethods = ['POST'])
    {
    }

    public function process(array $requestData, callable $next): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        if (! \in_array($method, $this->allowedMethods, true)) {
            JsonResponse::error('Methode nicht erlaubt.', 405);

            return null; // Pipeline abbrechen
        }

        return $next($requestData);
    }
}
