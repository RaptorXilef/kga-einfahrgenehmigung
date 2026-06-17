<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\MagicLinkService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/HistorySubmitCodeAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistorySubmitCodeAction implements ViewActionInterface
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $post          = $requestData['post'];
        $ip            = $requestData['ip'];
        $verifiedEmail = $this->magicLinkService->verifyAny((string) ($post['login_code'] ?? ''));

        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($ip);
            \session_regenerate_id(true);
            $_SESSION['user_history_email'] = $verifiedEmail;
            \header('Location: history.php');
            exit;
        }

        $this->rateLimiter->recordFailedAttempt($ip);
        \header('Location: history.php?sent=1&msg=' . \urlencode('Der Code ist ungültig oder abgelaufen.'));
        exit;
    }
}
