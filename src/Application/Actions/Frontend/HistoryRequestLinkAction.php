<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\HistoryRequestLinkRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\PermitService;
use App\Modules\Identity\Application\UseCases\RequestMagicLink\RequestMagicLinkCommand;
use App\Modules\Identity\Application\UseCases\RequestMagicLink\RequestMagicLinkHandler;

/**
 * Action für die Anforderung eines Magic-Links zur Historie.
 */
#[Route('GET', '/history_request_link')]
#[Route('POST', '/history_request_link')]
final readonly class HistoryRequestLinkAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private RequestMagicLinkHandler $requestHandler, // <-- CQRS
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = HistoryRequestLinkRequest::fromRequest($request);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history');
        }

        // Cross-Module Check: Hat die E-Mail überhaupt Genehmigungen?
        $permits = $this->permitService->getHistoryByEmail($dto->email);

        if ($permits === []) {
            $this->rateLimiter->recordFailedAttempt($dto->ip);
        } else {
            $this->rateLimiter->clearAttempts($dto->ip);

            // CQRS Command
            $command = new RequestMagicLinkCommand($dto->email);
            $this->requestHandler->handle($command);
        }

        $this->sessionManager->addFlash('success', 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.');

        return new RedirectResponse('history?sent=1');
    }
}
