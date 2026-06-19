<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\VerificationActionFactory;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;
use App\Contracts\Security\RateLimiterInterface;

/**
 * Controller für den Verifizierungsprozess (Double-Opt-In).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VerificationController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private RateLimiterInterface $rateLimiter,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private VerificationActionFactory $factory,
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
        $pipeline->add(new RateLimitMiddleware($this->rateLimiter, 'verify.php?error=1'));
        $pipeline->add(new CsrfMiddleware('verify.php?error=1'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process([
            'get'  => $get,
            'post' => $post,
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ], function (array $req): void {
            $action = $this->factory->create($req['get'], $req['post']);
            $result = $action->execute($req);

            // Response-Objekt abfangen!
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }
}
