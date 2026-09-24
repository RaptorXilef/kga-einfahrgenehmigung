<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use DomainException;
use InvalidArgumentException;
use Override;

/**
 * Zentrales Catch-All für fachliche Validierungsfehler bei Formularen.
 * Ersetzt try/catch-Blöcke in den Actions und implementiert das PRG-Pattern (Post/Redirect/Get).
 */
final readonly class FormExceptionHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionManager $sessionManager)
    {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        try {
            return $next($request);
        } catch (DomainException|InvalidArgumentException $e) {
            $isApi = \str_starts_with($request->getPath(), '/api/')
                || \str_contains($request->getHeader('Accept'), 'application/json');

            // API Requests werden nicht umgeleitet, sondern als JSON Error vom GlobalExceptionHandler verarbeitet
            if ($isApi) {
                throw $e;
            }

            if ($request->getMethod() === 'POST') {
                $postData = $request->post;

                // Sicherheitsrelevante und technische Felder nicht in die Session übernehmen
                unset(
                    $postData['csrf_token'],
                    $postData['action'],
                    $postData['hp_contact_website'],
                    $postData['pass'],
                    $postData['password'],
                    $postData['password_repeat'],
                    $postData['old_password'],
                    $postData['new_password'],
                    $postData['confirm_password'],
                );

                if ($postData !== []) {
                    $this->sessionManager->setFormData($postData);
                }

                $this->sessionManager->addFlash('error', $e->getMessage());

                $referer = $request->getHeader('Referer');
                $fallbackUrl = $referer !== '' ? $referer : '/';

                return new RedirectResponse($fallbackUrl);
            }

            throw $e;
        }
    }
}
