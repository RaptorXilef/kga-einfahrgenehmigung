<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\SystemCronAction;
use App\Application\Response\RedirectResponse;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
