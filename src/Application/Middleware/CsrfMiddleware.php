<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\RedirectResponse;
use App\Application\Security\CsrfHelper;
use App\Contracts\Application\MiddlewareInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $fallbackUrl,
    ) {
    }

    public function process(array $requestData, callable $next): mixed
    {
        // CSRF greift nur bei POST-Requests!
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = $requestData['post'] ?? [];

            if (! CsrfHelper::verify($post)) {
                $msg = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                $url = $this->fallbackUrl . (\str_contains($this->fallbackUrl, '?') ? '&' : '?') . 'msg=' . \urlencode($msg);

                return new RedirectResponse($url);
            }
        }

        // Alles okay! Weiter zur nächsten Schicht.
        return $next($requestData);
    }
}
