<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Routing\UniversalActionFactory;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ResponseInterface;

/**
 * Front Controller für den öffentlichen Genehmigungs-Beantragungsprozess.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private UniversalActionFactory $actionFactory,
        private SessionManager $sessionManager,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
    ) {
    }

    /**
     * Haupt-Request-Handler.
     */
    public function handleRequest(ServerRequest $request): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }

        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard)
            ->add(new CsrfMiddleware($this->sessionManager, 'index.php'))
            ->add($this->analyticsMiddleware)
            ->add($this->mailQueueMiddleware);

        // Mache Keys global eindeutig für den Universal-Router
        $actionKey = 'permit_render';
        if ($request->getMethod() === 'POST') {
            $actionKey = 'permit_submit';
        } elseif (isset($request->get['edit'], $request->get['token'])) {
            $actionKey = 'permit_edit';
        }

        $response = $pipeline->process($request, function (ServerRequest $req) use ($actionKey): mixed {
            return $this->actionFactory->create($actionKey)->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
