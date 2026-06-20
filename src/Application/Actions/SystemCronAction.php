<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\TextResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\Maintenance\CronScheduler;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCronAction implements ViewActionInterface
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
        } catch (\Throwable $e) {
            \error_log('Cron Execution Error: ' . $e->getMessage()); // Fehler ins Log...

            // ... aber NIEMALS den Stacktrace oder Pfade an den HTTP-Client senden!
            return new TextResponse("Status 500: Interner Fehler bei der Ausführung. Details im System-Log.\n", 500);
        }
    }
}
