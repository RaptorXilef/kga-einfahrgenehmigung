<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\HistorySubmitCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\MagicLinkService;

/**
 * Action für das Absenden des Verifizierungscodes im Portal.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('history_submit_code')]
final readonly class HistorySubmitCodeAction implements ViewActionInterface
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
        try {
            $dto = HistorySubmitCodeRequest::fromRequest($request);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history.php?sent=1');
        }

        $verifiedEmail = $this->magicLinkService->verifyAny($dto->loginCode);

        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($dto->ip);
            $this->sessionManager->regenerate();
            $this->sessionManager->setHistoryEmail($verifiedEmail);

            $this->auditLogger->log('USER_HISTORY_LOGIN', "Pächter (Email: {$verifiedEmail}) hat sich im Genehmigungsverlauf eingeloggt.");

            return new RedirectResponse('history.php');
        }

        $this->rateLimiter->recordFailedAttempt($dto->ip);
        $this->sessionManager->addFlash('error', 'Der Code ist ungültig oder abgelaufen.');

        return new RedirectResponse('history.php?sent=1');
    }
}
