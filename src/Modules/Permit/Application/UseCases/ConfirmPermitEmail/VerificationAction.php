<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Security\RateLimiterInterface;
use DomainException;
use Override;

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

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $token = \trim((string) ($request->get['token'] ?? $request->post['verification_code'] ?? ''));

        if ($token === '') {
            $html = $this->renderer->render('frontend/verify_input', ['isError' => isset($request->get['error'])]);

            return new HtmlResponse($html);
        }

        $ip = $request->getIp();

        try {
            $command = new ConfirmPermitEmailCommand($token, $ip);
            $resultTokenOrCode = $this->confirmHandler->handle($command);

            $this->rateLimiter->clearAttempts($ip);

            // Flow Control: Ein Checkout-Token (via random_bytes) hat 64 Zeichen. Ein Permit-Code ist viel kürzer.
            if (\strlen($resultTokenOrCode) > 32) {
                return new RedirectResponse('checkout?token=' . $resultTokenOrCode . '&verified=1');
            }

            return new RedirectResponse('check?code=' . $resultTokenOrCode . '&verified=1');
        } catch (DomainException $e) {
            $this->rateLimiter->recordFailedAttempt($ip);
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('verify?error=1');
        }
    }
}
