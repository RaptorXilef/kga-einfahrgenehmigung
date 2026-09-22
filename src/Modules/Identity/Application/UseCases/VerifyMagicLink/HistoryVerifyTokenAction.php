<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleTokenRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;

#[Route('GET', '/history_verify_token')]
#[Route('POST', '/history_verify_token')]
final readonly class HistoryVerifyTokenAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private VerifyMagicLinkHandler $verifyHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $ip = $request->getIp();

        try {
            $dto = SimpleTokenRequest::fromArray($request->get);
        } catch (ValidationException $e) {
            $this->rateLimiter->recordFailedAttempt($ip);
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history?sent=1');
        }

        try {
            $command = new VerifyMagicLinkCommand($dto->token, $ip);
            $this->verifyHandler->handle($command);

            $verifiedEmail = $this->sessionManager->getHistoryEmail();
            $this->auditLogger->log('USER_HISTORY_LOGIN', "Pächter (Email: {$verifiedEmail}) hat sich via Magic-Link im Genehmigungsverlauf eingeloggt.");

            return new RedirectResponse('history');

        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history?sent=1');
        }
    }
}
