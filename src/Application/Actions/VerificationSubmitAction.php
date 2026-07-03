<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\VerificationSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Entity\Permit;
use App\Core\Service\PermitService;

/**
 * Action zur Verarbeitung des übermittelten Verifizierungscodes (Token oder OTP).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VerificationSubmitAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = VerificationSubmitRequest::fromRequest($request);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('verify.php?error=1');
        }

        $result = $this->permitService->confirmEmail($dto->token);

        if ($result === null) {
            $this->rateLimiter->recordFailedAttempt($dto->ip);
            $this->sessionManager->addFlash('error', 'Code ungültig oder abgelaufen.');

            return new RedirectResponse('verify.php?error=1');
        }

        $this->rateLimiter->clearAttempts($dto->ip);

        if (isset($result['finalised']) && $result['finalised'] instanceof Permit) {
            return new RedirectResponse('check.php?code=' . $result['finalised']->code . '&verified=1');
        }

        if (\is_array($result)) {
            $redirectToken = $result['actual_token'] ?? $dto->token;

            return new RedirectResponse('checkout.php?token=' . $redirectToken . '&verified=1');
        }

        $this->sessionManager->addFlash('error', 'Fehler bei der Verifizierung.');

        return new RedirectResponse('verify.php?error=1');
    }
}
