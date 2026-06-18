<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\HistorySubmitCodeRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\MagicLinkService;

/**
 * Action für das Absenden des Verifizierungscodes im Portal.
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
        try {
            // IP-Isolierung ins DTO verlagert
            $dto = HistorySubmitCodeRequest::fromRequestData($requestData);
        } catch (ValidationException $e) {
            \header('Location: history.php?sent=1&msg=' . \urlencode($e->getMessage()));
            exit;
        }

        $verifiedEmail = $this->magicLinkService->verifyAny($dto->loginCode);

        if ($verifiedEmail) {
            $this->rateLimiter->clearAttempts($dto->ip);
            \session_regenerate_id(true);
            $_SESSION['user_history_email'] = $verifiedEmail;
            \header('Location: history.php');
            exit;
        }

        $this->rateLimiter->recordFailedAttempt($dto->ip);
        \header('Location: history.php?sent=1&msg=' . \urlencode('Der Code ist ungültig oder abgelaufen.'));
        exit;
    }
}
