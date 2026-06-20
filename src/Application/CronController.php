<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemCronAction;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\CronAuthMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Contracts\Application\ResponseInterface;
use App\Contracts\Config\ConfigInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CronController
{
    public function __construct(
        private ConfigInterface $config,
        private SystemCronAction $action,
    ) {
    }

    public function handleRequest(ServerRequest $request): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new CronAuthMiddleware($this->config));

        $response = $pipeline->process($request, function (ServerRequest $req): mixed {
            return $this->action->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
