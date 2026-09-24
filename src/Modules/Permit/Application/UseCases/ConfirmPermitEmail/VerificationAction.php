<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Security\RateLimiterInterface;

#[Route('GET', '/verify')]
#[Route('POST', '/verify')]
final readonly class VerificationAction implements ViewActionInterface
{
    public function __construct(
        private ConfirmPermitEmailHandler $confirmHandler,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $token = \trim((string) ($request->get['token'] ?? $request->post['verification_code'] ?? ''));

        if ($token === '') {
            $html = $this->renderer->render('frontend/verify_input', ['isError' => isset($request->get['error'])]);

            return new HtmlResponse($html);
        }

        $ip = $request->getIp();

        $command = new ConfirmPermitEmailCommand($token);
        $this->confirmHandler->handle($command);
        $result = $command->context;

        if (!$result->isSuccess) {
            $this->rateLimiter->recordFailedAttempt($ip);
            $this->sessionManager->addFlash('error', 'Code ungültig oder abgelaufen.');

            return new RedirectResponse('verify?error=1');
        }

        $this->rateLimiter->clearAttempts($ip);

        // VSA CQRS FIX: Prüfung jetzt gegen string statt Entity
        if (\is_string($result->finalisedPermitCode)) {
            return new RedirectResponse('check?code=' . $result->finalisedPermitCode . '&verified=1');
        }

        if ($result->checkoutToken !== null) {
            return new RedirectResponse('checkout?token=' . $result->checkoutToken . '&verified=1');
        }

        $this->sessionManager->addFlash('error', 'Fehler bei der Verifizierung.');

        return new RedirectResponse('verify?error=1');
    }
}
