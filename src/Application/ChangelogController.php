<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemChangelogAction;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\TerminateMailQueueMiddleware;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/ChangelogController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ChangelogController
{
    public function __construct(
        private SystemChangelogAction $action,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
    ) {
    }

    public function handleRequest(array $get): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process(['get' => $get], function (array $req): void {
            $this->action->execute($req);
        });
    }
}
