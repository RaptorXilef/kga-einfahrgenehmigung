<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\VerificationSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Entity\Permit;
use App\Core\Service\PermitService;

/**
 * Action zur Verarbeitung des übermittelten Verifizierungscodes (Token oder OTP).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationSubmitAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $dto = VerificationSubmitRequest::fromRequestData($requestData);
        } catch (ValidationException $e) {
            return new RedirectResponse('verify.php?error=1&msg=' . \urlencode($e->getMessage()));
        }
        $result = $this->permitService->confirmEmail($dto->token);
        if ($result === null) {
            $this->rateLimiter->recordFailedAttempt($dto->ip);

            return new RedirectResponse('verify.php?error=1&msg=' . \urlencode('Code ungültig oder abgelaufen.'));
        }
        $this->rateLimiter->clearAttempts($dto->ip);
        if (isset($result['finalised']) && $result['finalised'] instanceof Permit) {
            return new RedirectResponse('check.php?code=' . $result['finalised']->code . '&verified=1');
        }
        if (\is_array($result)) {
            $redirectToken = $result['actual_token'] ?? $dto->token;

            return new RedirectResponse('checkout.php?token=' . $redirectToken . '&verified=1');
        }

        return new RedirectResponse('verify.php?error=1&msg=' . \urlencode('Fehler bei der Verifizierung.'));
    }
}
