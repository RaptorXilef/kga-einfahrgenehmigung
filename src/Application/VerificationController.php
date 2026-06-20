<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\VerificationActionFactory;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ResponseInterface;
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
        private SessionManager $sessionManager,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private VerificationActionFactory $factory,
    ) {
    }

    /**
     * Haupt-Request-Handler für den Double-Opt-In-Prozess.
     */
    public function handleRequest(ServerRequest $request): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new RateLimitMiddleware($this->rateLimiter, 'verify.php?error=1'));
        $pipeline->add(new CsrfMiddleware($this->sessionManager, 'verify.php?error=1'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        // ROUTING LOGIK
        $actionKey = (isset($request->get['token']) || isset($request->post['submit_code'])) ? 'submit' : 'render';

        $response = $pipeline->process($request, function (ServerRequest $req) use ($actionKey): mixed {
            $action = $this->factory->create($actionKey);

            return $action->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
