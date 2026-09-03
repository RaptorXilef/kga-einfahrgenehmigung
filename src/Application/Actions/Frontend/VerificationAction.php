<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Entity\Permit;
use App\Core\Service\PermitService;
use Throwable;

/**
 * Kombinierte Action für das Rendern der Eingabemaske und die Verarbeitung
 * von Verifizierungscodes (aus E-Mail-Links oder manueller Formular-Eingabe).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/verify')]
#[Route('POST', '/verify')]
final readonly class VerificationAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private MailServiceInterface $mailService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        // 1. Suche nach dem Token: Entweder aus der URL (GET via E-Mail) oder dem Formular (POST)
        $token = \trim((string) ($request->get['token'] ?? $request->post['verification_code'] ?? ''));

        // 2. Wenn kein Token vorhanden ist -> Zeige das leere Eingabeformular
        if ($token === '') {
            $this->renderer->render('frontend/verify_input', [
                'isError' => isset($request->get['error']),
            ]);

            return null;
        }

        // --- Ab hier: Ein Token wurde gesendet, wir prüfen es! ---
        $ip = $request->getIp();
        $result = $this->permitService->confirmEmail($token);

        // Fall A: Token ist komplett ungültig
        if ($result === null) {
            $this->rateLimiter->recordFailedAttempt($ip);
            $this->sessionManager->addFlash('error', 'Code ungültig oder abgelaufen.');

            return new RedirectResponse('verify.php?error=1');
        }

        $this->rateLimiter->clearAttempts($ip);

        // Fall B: Antrag war kostenlos (oder 100% Gutschein) und wurde sofort aktiv
        if (isset($result['finalised']) && $result['finalised'] instanceof Permit) {
            $this->dispatchMailsImmediately();

            return new RedirectResponse('check.php?code=' . $result['finalised']->code->value . '&verified=1');
        }

        // Fall C: Antrag ist bestätigt und bereit zur Zahlung (Checkout)
        if (\is_array($result)) {
            $this->dispatchMailsImmediately();
            $redirectToken = $result['actual_token'] ?? $token;

            return new RedirectResponse('checkout.php?token=' . $redirectToken . '&verified=1');
        }

        // Sicherheits-Fallback
        $this->sessionManager->addFlash('error', 'Fehler bei der Verifizierung.');

        return new RedirectResponse('verify.php?error=1');
    }

    /**
     * Versucht die generierten Bestätigungs-/Genehmigungs-Mails sofort zu versenden.
     */
    private function dispatchMailsImmediately(): void
    {
        try {
            $this->mailService->processQueue(5);
        } catch (Throwable $e) {
            \error_log('Immediate Mail Dispatch Error: ' . $e->getMessage());
        }
    }
}
