<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Maintenance\UpdateMigrationService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemFinalizeUpdateAction implements ViewActionInterface
{
    public function __construct(private UpdateMigrationService $migrationService, private AuthService $auth)
    {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $executedScripts = $this->migrationService->runAllPending();
            $this->auth->refreshSessionPermissions($this->auth->getGroup());

            $msg = empty($executedScripts)
                ? 'Update abgeschlossen. System ist auf dem neuesten Stand.'
                : 'Update abgeschlossen. Datenbank aktualisiert: ' . \implode(', ', $executedScripts);

            JsonResponse::success(['message' => $msg, 'executed' => $executedScripts]);
        } catch (\Throwable $e) {
            JsonResponse::error('Fehler bei der Datenbank-Migration: ' . $e->getMessage());
        }

        return null;
    }
}
