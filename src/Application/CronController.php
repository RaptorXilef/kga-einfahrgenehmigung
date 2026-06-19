<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemCronAction;
use App\Application\Response\RedirectResponse;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CronController
{
    public function __construct(
        private SystemCronAction $action,
    ) {
    }

    public function handleRequest(array $get): void
    {
        $result = $this->action->execute(['get' => $get]);

        // Response-Objekt abfangen!
        if ($result instanceof RedirectResponse) {
            $result->send();
        }
    }
}
