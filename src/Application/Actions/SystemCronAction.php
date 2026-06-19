<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\TextResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\Maintenance\CronScheduler;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCronAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private CronScheduler $cron,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $this->cron->runForce();

            return new TextResponse("Status 200 OK: Cronjobs (Archivierung & Backup) erfolgreich ausgeführt.\n");
        } catch (\Throwable $e) {
            return new TextResponse('Fehler bei der Ausführung: ' . $e->getMessage() . "\n", 500);
        }
    }
}
