<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\TextResponse;
use App\Core\Service\Maintenance\CronScheduler;
use Throwable;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/cron')]
#[Route('POST', '/cron')]
final readonly class CronAction implements ViewActionInterface
{
    public function __construct(
        private CronScheduler $cron,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $this->cron->runForce();

            return new TextResponse("Status 200 OK: Cronjobs (Archivierung & Backup) erfolgreich ausgeführt.\n");
        } catch (Throwable $e) {
            \error_log('Cron Execution Error: ' . $e->getMessage()); // Fehler ins Log...

            // ... aber NIEMALS den Stacktrace oder Pfade an den HTTP-Client senden!
            return new TextResponse("Status 500: Interner Fehler bei der Ausführung. Details im System-Log.\n", 500);
        }
    }
}
