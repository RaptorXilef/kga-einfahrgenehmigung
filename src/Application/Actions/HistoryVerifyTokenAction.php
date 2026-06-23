<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleTokenRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\MagicLinkService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryVerifyTokenAction implements ViewActionInterface
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $ip = $request->getIp();

        try {
            $dto = SimpleTokenRequest::fromArray($request->get);
        } catch (ValidationException $e) {
            $this->rateLimiter->recordFailedAttempt($ip);

            return new RedirectResponse('history.php?sent=1&msg=' . \urlencode($e->getMessage()));
        }

        $verifiedEmail = $this->magicLinkService->verifyAny($dto->token);

        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($ip);
            $this->sessionManager->regenerate();
            $this->sessionManager->setHistoryEmail($verifiedEmail);

            return new RedirectResponse('history.php');
        }

        $this->rateLimiter->recordFailedAttempt($ip);

        return new RedirectResponse('history.php?sent=1&msg=' . \urlencode(
            'Der Link ist ungültig oder abgelaufen.',
        ));
    }
}
