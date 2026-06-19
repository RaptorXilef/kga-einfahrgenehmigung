<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;
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
    ) {
    }

    public function handleRequest(ViewActionInterface $action, array $get, array $post = []): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process(['get' => $get, 'post' => $post], function (array $req) use ($action): void {
            $result = $action->execute($req);

            // Response-Objekt abfangen!
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }
}
