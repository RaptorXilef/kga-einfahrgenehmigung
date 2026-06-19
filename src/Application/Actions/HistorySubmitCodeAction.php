<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\HistorySubmitCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\MagicLinkService;

/**
 * Action für das Absenden des Verifizierungscodes im Portal.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistorySubmitCodeAction implements ViewActionInterface
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $dto = HistorySubmitCodeRequest::fromRequestData($requestData);
        } catch (ValidationException $e) {
            return new RedirectResponse('history.php?sent=1&msg=' . \urlencode($e->getMessage()));
        }
        $verifiedEmail = $this->magicLinkService->verifyAny($dto->loginCode);
        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($dto->ip);
            \session_regenerate_id(true);
            $_SESSION['user_history_email'] = $verifiedEmail;

            return new RedirectResponse('history.php');
        }
        $this->rateLimiter->recordFailedAttempt($dto->ip);

        return new RedirectResponse('history.php?sent=1&msg=' . \urlencode(
            'Der Code ist ungültig oder abgelaufen.'));
    }
}
