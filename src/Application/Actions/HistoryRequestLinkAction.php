<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\HistoryRequestLinkRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;

/**
 * Action für die Anforderung eines Magic-Links zur Historie.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryRequestLinkAction implements ViewActionInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MagicLinkService $magicLinkService,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $dto = HistoryRequestLinkRequest::fromRequestData($requestData);
        } catch (ValidationException $e) {
            return new RedirectResponse('history.php?sent=0&msg=' . \urlencode($e->getMessage()));
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

        return new RedirectResponse('history.php?sent=1&msg=' . \urlencode(
            'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.',
        ));
    }
}
