<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemCronAction;
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

    public function handleRequest(array $get): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new CronAuthMiddleware($this->config));

        $response = $pipeline->process(['get' => $get], function (array $req): mixed {
            return $this->action->execute(['get' => $req['get']]);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
