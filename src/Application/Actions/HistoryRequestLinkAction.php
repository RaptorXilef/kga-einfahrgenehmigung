<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\HistoryRequestLinkRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;

/**
 * Action für die Anforderung eines Magic-Links zur Historie.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('history_request_link')]
final readonly class HistoryRequestLinkAction implements ViewActionInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MagicLinkService $magicLinkService,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = HistoryRequestLinkRequest::fromRequest($request);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history.php');
        }

        $permits = $this->permitService->getHistoryByEmail($dto->email);
        if ($permits === []) {
            $this->rateLimiter->recordFailedAttempt($dto->ip);
        } else {
            $this->rateLimiter->clearAttempts($dto->ip);
            $data = $this->magicLinkService->createToken($dto->email);

            $this->eventDispatcher->dispatch(new MagicLinkRequestedEvent(
                $dto->email,
                $data['token'],
                $data['code'],
            ));
        }

        $this->sessionManager->addFlash('success', 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.');

        return new RedirectResponse('history.php?sent=1');
    }
}
