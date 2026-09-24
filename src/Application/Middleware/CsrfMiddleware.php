<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use Override;

final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private string $fallbackUrl,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $method = $request->getMethod();

        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $provided = $request->getHeader('X-CSRF-Token') ?: ($request->post['csrf_token'] ?? '');
            $stored = $this->sessionManager->getCsrfToken();

            if ($stored === '' || !\hash_equals($stored, $provided)) {
                // UX-Rettung: Wir speichern die eingegebenen Formulardaten zwischen,
                // bevor wir die Anfrage ablehnen.
                $postData = $request->post;
                unset($postData['csrf_token'], $postData['action']);

                if ($postData !== []) {
                    $this->sessionManager->setFormData($postData);
                }

                $this->sessionManager->addFlash('error', 'Ihre Sitzung ist abgelaufen. Zu Ihrer Sicherheit wurde die Seite neu geladen. Ihre Eingaben wurden wiederhergestellt - bitte senden Sie das Formular erneut ab.');

                return new RedirectResponse($this->fallbackUrl);
            }
        }

        // Alles okay! Weiter zur nächsten Schicht.
        return $next($request);
    }
}
