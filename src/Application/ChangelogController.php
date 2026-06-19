<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemChangelogAction;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Contracts\Application\ResponseInterface;
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
    ) {
    }

    public function handleRequest(array $get): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new RequireLoginMiddleware($this->auth, 'index.php'));
        $pipeline->add(new PermissionMiddleware($this->auth, 'system.update.view', 'index.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $response = $pipeline->process(['get' => $get], function (array $req): mixed {
            return $this->action->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
