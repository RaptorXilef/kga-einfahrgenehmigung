<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\Maintenance\CronScheduler;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/SystemCronAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemCronAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private CronScheduler $cron,
    ) {
    }

    public function execute(array $requestData): void
    {
        $providedToken = $requestData['get']['token'] ?? '';
        $requiredToken = (string) $this->config->get('cron_secret', 'unconfigured');

        if (\php_sapi_name() !== 'cli' && $providedToken !== $requiredToken) {
            \http_response_code(403);
            exit('Forbidden: Ungültiges Token.');
        }

        try {
            $this->cron->runForce();
            echo "Status 200 OK: Cronjobs (Archivierung & Backup) erfolgreich ausgeführt.\n";
        } catch (\Throwable $e) {
            \http_response_code(500);
            echo 'Fehler bei der Ausführung: ' . $e->getMessage() . "\n";
        }
    }
}
