<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\VerificationSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Entity\Permit;
use App\Core\Service\PermitService;

/**
 * Action zur Verarbeitung des übermittelten Verifizierungscodes (Token oder OTP).
 *
 * Path: src/Application/Actions/VerificationSubmitAction.php
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

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $ip = $requestData['ip'];

        if ($this->rateLimiter->isBlocked($ip)) {
            \header('Location: verify.php?error=1&msg=' . \urlencode('Zu viele Versuche. IP gesperrt.'));
            exit;
        }

        try {
            $dto = VerificationSubmitRequest::fromRequestData($requestData);
        } catch (ValidationException $e) {
            \header('Location: verify.php?error=1&msg=' . \urlencode($e->getMessage()));
            exit;
        }

        $result = $this->permitService->confirmEmail($dto->token);

        if ($result === null) {
            $this->rateLimiter->recordFailedAttempt($ip);
            \header('Location: verify.php?error=1&msg=' . \urlencode('Code ungültig oder abgelaufen.'));
            exit;
        }

        $this->rateLimiter->clearAttempts($ip);

        // Fall A: Sofort finalisiert (z.B. durch Gutschein)
        if (isset($result['finalised']) && $result['finalised'] instanceof Permit) {
            \header('Location: check.php?code=' . $result['finalised']->code . '&verified=1');
            exit;
        }

        // Fall B: Nur E-Mail bestätigt, wartet nun auf Zahlung
        if (\is_array($result)) {
            $redirectToken = $result['actual_token'] ?? $dto->token;
            \header('Location: checkout.php?token=' . $redirectToken . '&verified=1');
            exit;
        }

        \header('Location: verify.php?error=1&msg=' . \urlencode('Fehler bei der Verifizierung.'));
        exit;
    }
}
