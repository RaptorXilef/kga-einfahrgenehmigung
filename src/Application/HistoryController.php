<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\HistoryActionFactory;
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
 * Front Controller für die historische Antragsübersicht von Endnutzern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private HistoryActionFactory $actionFactory,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
    ) {
    }

    /**
     * Haupt-Request-Handler für die Benutzerhistorie.
     */
    public function handleRequest(ServerRequest $request): void
    {
        // 1. Zwiebelschalen aufbauen
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new RateLimitMiddleware($this->rateLimiter, 'history.php'));
        $pipeline->add(new CsrfMiddleware($this->sessionManager, 'history.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        // ROUTING LOGIK
        $actionKey = 'render';
        if (isset($request->post['action']) && $request->post['action'] === 'logout') {
            $actionKey = 'logout';
        } elseif (isset($request->post['request_link'])) {
            $actionKey = 'request_link';
        } elseif (isset($request->post['submit_code'])) {
            $actionKey = 'submit_code';
        } elseif (isset($request->get['token'])) {
            $actionKey = 'verify_token';
        } elseif (isset($request->get['action'], $request->get['code']) && $request->get['action'] === 'print') {
            $actionKey = 'print';
        }

        // 2. Request durchschicken
        $response = $pipeline->process($request, function (ServerRequest $req) use ($actionKey): mixed {
            $action = $this->actionFactory->create($actionKey);

            return $action->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
