<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiCsrfMiddleware implements MiddlewareInterface
{
    public function process(array $requestData, callable $next): mixed
    {
        $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($requestData['post']['csrf_token'] ?? '');
        $sessionToken  = $_SESSION['csrf_token'] ?? '';

        if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
            JsonResponse::unauthorized('Fehler: Ungültiges Sicherheits-Token (CSRF).');

            return null;
        }

        return $next($requestData);
    }
}
