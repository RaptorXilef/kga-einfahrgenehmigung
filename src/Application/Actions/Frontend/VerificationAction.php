<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Permit\Application\UseCases\ConfirmPermitEmail\ConfirmPermitEmailCommand;
use App\Modules\Permit\Application\UseCases\ConfirmPermitEmail\ConfirmPermitEmailHandler;

/**
 * Kombinierte Action für das Rendern der Eingabemaske und die Verarbeitung
 * von Verifizierungscodes (aus E-Mail-Links oder manueller Formular-Eingabe).
 */
#[Route('GET', '/verify')]
#[Route('POST', '/verify')]
final readonly class VerificationAction implements ViewActionInterface
{
    public function __construct(
        private ConfirmPermitEmailHandler $confirmHandler, // <-- CQRS
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        // 1. Suche nach dem Token: Entweder aus der URL (GET via E-Mail) oder dem Formular (POST)
        $token = \trim((string) ($request->get['token'] ?? $request->post['verification_code'] ?? ''));

        // 2. Wenn kein Token vorhanden ist -> Zeige das leere Eingabeformular
        if ($token === '') {
            $html = $this->renderer->render('frontend/verify_input', ['isError' => isset($request->get['error'])]);

            return new HtmlResponse($html);
        }

        // --- Ab hier: Ein Token wurde gesendet, wir prüfen es! ---
        $ip = $request->getIp();
        $result = $this->confirmHandler->handle(new ConfirmPermitEmailCommand($token));

        // Fall A: Token ist komplett ungültig
        if (!$result->isSuccess) {
            $this->rateLimiter->recordFailedAttempt($ip);
            $this->sessionManager->addFlash('error', 'Code ungültig oder abgelaufen.');

            return new RedirectResponse('verify?error=1');
        }

        $this->rateLimiter->clearAttempts($ip);

        // Fall B: Kostenlos / 100% Gutschein
        if ($result->finalisedPermit !== null) {
            return new RedirectResponse('check?code=' . $result->finalisedPermit->code->value . '&verified=1');
        }

        // Fall C: Antrag ist bestätigt und bereit zur Zahlung (Checkout)
        if ($result->checkoutToken !== null) {
            return new RedirectResponse('checkout?token=' . $result->checkoutToken . '&verified=1');
        }

        // Sicherheits-Fallback
        $this->sessionManager->addFlash('error', 'Fehler bei der Verifizierung.');

        return new RedirectResponse('verify?error=1');
    }
}
