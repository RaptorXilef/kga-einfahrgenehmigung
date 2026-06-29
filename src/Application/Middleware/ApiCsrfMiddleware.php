<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\MiddlewareInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiCsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        // CSRF-Prüfung nur bei schreibenden HTTP-Methoden durchführen
        $method = $request->getMethod() ?? 'GET';

        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $providedToken = $request->getHeader('X-CSRF-Token') ?: ($request->post['csrf_token'] ?? '');
            $sessionToken  = $this->sessionManager->getCsrfToken();

            if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
                return JsonResponse::unauthorized('Fehler: Ungültiges Sicherheits-Token (CSRF).');
            }
        }

        return $next($request);
    }
}
