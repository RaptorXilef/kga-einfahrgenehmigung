<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemChangelogAction;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ChangelogController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private SystemChangelogAction $action,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
    ) {
    }

    public function handleRequest(array $get): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process(['get' => $get], function (array $req): void {
            $result = $this->action->execute($req);

            // Response-Objekt abfangen!
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }
}
