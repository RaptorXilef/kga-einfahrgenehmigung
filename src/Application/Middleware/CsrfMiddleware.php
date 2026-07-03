<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\MiddlewareInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private string $fallbackUrl,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        // CSRF greift nur bei POST-Requests!
        $method = $request->getMethod() ?? 'GET';
        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $provided = $request->getHeader('X-CSRF-Token') ?: ($request->post['csrf_token'] ?? '');
            $stored   = $this->sessionManager->getCsrfToken();

            if ($stored === '' || ! \hash_equals($stored, $provided)) {
                $this->sessionManager->addFlash('error', 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.');

                return new RedirectResponse($this->fallbackUrl);
            }
        }

        // Alles okay! Weiter zur nächsten Schicht.
        return $next($request);
    }
}
