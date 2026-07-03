<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Routing\UniversalActionFactory;
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
        private UniversalActionFactory $actionFactory,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
    ) {
    }

    /**
     * Haupt-Request-Handler für die Benutzerhistorie.
     */
    public function handleRequest(ServerRequest $request): void
    {
        // 1. Zwiebelschalen aufbauen
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard)
            ->add(new RateLimitMiddleware($this->rateLimiter, $this->sessionManager, 'history.php'))
            ->add(new CsrfMiddleware($this->sessionManager, 'history.php'))
            ->add($this->analyticsMiddleware)
            ->add($this->mailQueueMiddleware);

        // Mache Keys global eindeutig für den Universal-Router
        $actionKey = 'history_render';
        if (isset($request->post['action']) && $request->post['action'] === 'logout') {
            $actionKey = 'history_logout';
        } elseif (isset($request->post['action']) && $request->post['action'] === 'cancel_permit') {
            $actionKey = 'history_cancel_permit';
        } elseif (isset($request->post['request_link'])) {
            $actionKey = 'history_request_link';
        } elseif (isset($request->post['submit_code'])) {
            $actionKey = 'history_submit_code';
        } elseif (isset($request->get['token'])) {
            $actionKey = 'history_verify_token';
        } elseif (isset($request->get['action'], $request->get['code']) && $request->get['action'] === 'print') {
            $actionKey = 'history_print';
        }

        // 2. Request durchschicken
        $response = $pipeline->process($request, function (ServerRequest $req) use ($actionKey): mixed {
            return $this->actionFactory->create($actionKey)->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
