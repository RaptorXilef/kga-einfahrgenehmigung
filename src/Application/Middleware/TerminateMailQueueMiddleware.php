<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Mail\MailQueueService;

/**
 * Führt Aufgaben aus, NACHDEM die eigentliche Action beendet wurde (Terminate Phase).
 *
 * Path: src/Application/Middleware/TerminateMailQueueMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class TerminateMailQueueMiddleware implements MiddlewareInterface
{
    public function __construct(private MailServiceInterface $mailService)
    {
    }

    public function process(array $requestData, callable $next): mixed
    {
        // 1. Zuerst die Action (und eventuelle weitere Middlewares) ausführen
        $response = $next($requestData);

        // 2. Danach (Terminate) die Warteschlange abarbeiten
        try {
            if ($this->mailService instanceof MailQueueService) {
                $this->mailService->processQueue(10);
            }
        } catch (\Throwable) {
            // Silently fail: Hintergrund-Prozesse dürfen die Nutzer-Response nicht crashen
        }

        return $response;
    }
}
