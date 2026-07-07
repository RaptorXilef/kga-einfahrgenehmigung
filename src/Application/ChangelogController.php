<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemChangelogAction;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ChangelogController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private AuthService $auth,
        private SystemChangelogAction $action,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
    ) {
    }

    public function handleRequest(ServerRequest $request): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard)
            ->add(new RequireLoginMiddleware($this->auth, 'index.php'));
        if ($this->action instanceof RequiresPermissionInterface) {
            $pipeline->add(new PermissionMiddleware(
                $this->auth,
                $this->action->getRequiredPermission(),
                'index.php',
            ));
        }

        $pipeline
            ->add($this->analyticsMiddleware)
            ->add($this->mailQueueMiddleware);

        $response = $pipeline->process($request, function (ServerRequest $req): mixed {
            return $this->action->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
