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
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;
use Throwable;

/**
 * Action für die Anforderung eines Magic-Links zur Historie.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/history_request_link')]
#[Route('POST', '/history_request_link')]
final readonly class HistoryRequestLinkAction implements ViewActionInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MagicLinkService $magicLinkService,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private MailServiceInterface $mailService,
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
            $this->dispatchMailsImmediately();
        }

        $this->sessionManager->addFlash('success', 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.');

        return new RedirectResponse('history.php?sent=1');
    }

    /**
     * Versucht die Magic-Link E-Mail sofort ohne Umweg über den CronJob zu versenden,
     * um dem Pächter direkt den Zugang zu ermöglichen.
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
