<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;

/**
 * Middleware zur Validierung der zulässigen HTTP-Methoden.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
