<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Entity\Permit;
use App\Core\Service\MailQueueService;
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
        private MailServiceInterface $mailService,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $get  = $requestData['get'];
        $post = $requestData['post'];
        $ip   = $requestData['ip'];

        $input = isset($get['token']) ? (string) $get['token'] : (string) ($post['verification_code'] ?? '');

        // Rate Limiting für öffentliche OTP-Eingaben
        if ($this->rateLimiter->isBlocked($ip)) {
            \header('Location: verify.php?error=1&msg=' . \urlencode('Zu viele Versuche. IP für 15 Minuten gesperrt.'));
            exit;
        }

        $result = $this->permitService->confirmEmail($input);

        if ($result === null) {
            $this->rateLimiter->recordFailedAttempt($ip);
            $msg = 'Der eingegebene Code oder Link ist ungültig bzw. bereits abgelaufen.';
            \header('Location: verify.php?error=1&msg=' . \urlencode($msg));
            exit;
        }

        $this->rateLimiter->clearAttempts($ip);

        if ($this->mailService instanceof MailQueueService) {
            $this->mailService->processQueue(3); // Dokumente sofort losschicken!
        }

        // Fall A: Sofort finalisiert (z.B. durch Gutschein)
        if (isset($result['finalised']) && $result['finalised'] instanceof Permit) {
            \header('Location: check.php?code=' . $result['finalised']->code . '&verified=1');
            exit;
        }

        // Fall B: Nur E-Mail bestätigt, wartet nun auf Zahlung
        if (\is_array($result)) {
            $redirectToken = $result['actual_token'] ?? $input;
            \header('Location: checkout.php?token=' . $redirectToken . '&verified=1');
            exit;
        }

        $msg = 'Der eingegebene Code oder Link ist ungültig bzw. bereits abgelaufen.';
        \header('Location: verify.php?error=1&msg=' . \urlencode($msg));
        exit;
    }
}
