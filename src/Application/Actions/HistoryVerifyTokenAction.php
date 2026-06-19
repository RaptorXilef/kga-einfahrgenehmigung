<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\MagicLinkService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryVerifyTokenAction implements ViewActionInterface
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        $get           = $requestData['get'];
        $ip            = $requestData['ip'];
        $verifiedEmail = $this->magicLinkService->verifyAny((string) ($get['token'] ?? ''));
        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($ip);
            \session_regenerate_id(true);
            $_SESSION['user_history_email'] = $verifiedEmail;

            return new RedirectResponse('history.php');
        }
        $this->rateLimiter->recordFailedAttempt($ip);

        return new RedirectResponse('history.php?sent=1&msg=' . \urlencode(
            'Der Link ist ungültig oder abgelaufen. Sie können den Code manuell eingeben.',
        ));
    }
}
