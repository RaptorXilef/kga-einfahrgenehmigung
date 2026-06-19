<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Contracts\Application\ViewActionInterface;

/**
 * Generischer Controller für einfache Frontend-Views, die durch eine Pipeline fließen müssen.
 *
 * Path: src/Application/FrontendController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class FrontendController
{
    public function __construct(private TerminateMailQueueMiddleware $mailQueueMiddleware)
    {
    }

    public function handleRequest(ViewActionInterface $action, array $get, array $post = []): void
    {
        $pipeline = new MiddlewarePipeline();

        // Fügt die Mail-Queue an das Ende der Ausführung an
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process(['get' => $get, 'post' => $post], function (array $req) use ($action): void {
            $action->execute($req);
        });
    }
}
