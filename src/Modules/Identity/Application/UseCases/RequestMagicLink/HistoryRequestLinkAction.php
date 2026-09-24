<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\RequestMagicLink;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Integration\PermitIntegrationInterface;
use App\Contracts\Security\RateLimiterInterface;
use Override;

#[Route('GET', '/history_request_link')]
#[Route('POST', '/history_request_link')]
final readonly class HistoryRequestLinkAction implements ViewActionInterface
{
    public function __construct(
        private PermitIntegrationInterface $permitIntegration,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private RequestMagicLinkHandler $requestHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = HistoryRequestLinkRequest::fromRequest($request);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history');
        }

        // Cross-Module Check: Hat die E-Mail überhaupt Genehmigungen?
        if (!$this->permitIntegration->hasPermits($dto->email)) {
            $this->rateLimiter->recordFailedAttempt($dto->ip);
        } else {
            $this->rateLimiter->clearAttempts($dto->ip);
            $this->requestHandler->handle(new RequestMagicLinkCommand($dto->email));
        }

        $this->sessionManager->addFlash('success', 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.');

        return new RedirectResponse('history?sent=1');
    }
}
