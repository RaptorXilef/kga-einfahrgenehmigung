<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\HistoryActionFactory;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;
use App\Contracts\Security\RateLimiterInterface;

/**
 * Front Controller für die historische Antragsübersicht von Endnutzern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private HistoryActionFactory $actionFactory,
        private RateLimiterInterface $rateLimiter,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
    ) {
    }

    /**
     * Haupt-Request-Handler für die Benutzerhistorie.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        // 1. Zwiebelschalen aufbauen
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new RateLimitMiddleware($this->rateLimiter, 'history.php'));
        $pipeline->add(new CsrfMiddleware('history.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        // 2. Request durchschicken
        $pipeline->process([
            'get'  => $get,
            'post' => $post,
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ], function (array $req): void {
            // Die Action wird nur erreicht, wenn RateLimit und CSRF erfolgreich passiert wurden!
            $action = $this->actionFactory->create($req['get'], $req['post']);
            $result = $action->execute($req);

            // Response-Objekt abfangen!
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }
}
