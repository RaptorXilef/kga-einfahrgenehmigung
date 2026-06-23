<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Contracts\Application\ResponseInterface;
use App\Contracts\Application\ViewActionInterface;

/**
 * Generischer Controller für einfache Frontend-Views.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class FrontendController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
    ) {
    }

    public function handleRequest(ViewActionInterface $action, ServerRequest $request): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard)
            ->add($this->analyticsMiddleware)
            ->add($this->mailQueueMiddleware);

        $response = $pipeline->process($request, function (ServerRequest $req) use ($action): mixed {
            return $action->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
