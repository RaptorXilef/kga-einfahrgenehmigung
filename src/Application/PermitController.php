<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\PermitActionFactory;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;

/**
 * Front Controller für den öffentlichen Genehmigungs-Beantragungsprozess.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private PermitActionFactory $actionFactory,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
    ) {
    }

    /**
     * Haupt-Request-Handler.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     * @param array<string, mixed> $get  Entspricht $_GET
     */
    public function handleRequest(array $post, array $get): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }

        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new CsrfMiddleware('index.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process(['post' => $post, 'get' => $get], function (array $req): void {
            $action = $this->actionFactory->create($req['get'], $req['post']);
            $result = $action->execute($req);

            // Response-Objekt abfangen!
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }
}
