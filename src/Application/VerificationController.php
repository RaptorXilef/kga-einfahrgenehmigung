<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\VerificationActionFactory;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\RateLimitMiddleware;
use App\Contracts\Security\RateLimiterInterface;

/**
 * Controller für den Verifizierungsprozess (Double-Opt-In).
 *
 * Path: src/Application/VerificationController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationController
{
    public function __construct(
        private VerificationActionFactory $factory,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    /**
     * Haupt-Request-Handler für den Double-Opt-In-Prozess.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        $pipeline = new MiddlewarePipeline();

        // Die Türsteher-Pipeline: Zuerst Rate-Limit, dann CSRF
        $pipeline
            ->add(new RateLimitMiddleware($this->rateLimiter, 'verify.php?error=1'))
            ->add(new CsrfMiddleware('verify.php?error=1'));

        $pipeline->process([
            'get'  => $get,
            'post' => $post,
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ], function (array $req): void {
            $action = $this->factory->create($req['get'], $req['post']);
            $action->execute($req);
        });
    }
}
