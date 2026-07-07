<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleTokenRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\MagicLinkService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('history_verify_token')]
final readonly class HistoryVerifyTokenAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history.php?sent=1');
        }

        $verifiedEmail = $this->magicLinkService->verifyAny($dto->token);

        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($ip);
            $this->sessionManager->regenerate();
            $this->sessionManager->setHistoryEmail($verifiedEmail);

            $this->auditLogger->log('USER_HISTORY_LOGIN', "Pächter (Email: {$verifiedEmail}) hat sich via Magic-Link im Genehmigungsverlauf eingeloggt.");

            return new RedirectResponse('history.php');
        }

        $this->rateLimiter->recordFailedAttempt($ip);
        $this->sessionManager->addFlash('error', 'Der Link ist ungültig oder abgelaufen.');

        return new RedirectResponse('history.php?sent=1');
    }
}
