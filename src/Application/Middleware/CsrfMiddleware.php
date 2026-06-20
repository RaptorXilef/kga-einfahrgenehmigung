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
        if ($request->getMethod() === 'POST') {
            $provided = $request->post['csrf_token'] ?? '';
            $stored   = $this->sessionManager->getCsrfToken();
            if ($stored === '' || ! \hash_equals($stored, $provided)) {
                $msg = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                $url = $this->fallbackUrl . (\str_contains($this->fallbackUrl, '?') ? '&' : '?') . 'msg=' . \urlencode($msg);

                return new RedirectResponse($url);
            }
        }

        // Alles okay! Weiter zur nächsten Schicht.
        return $next($request);
    }
}
