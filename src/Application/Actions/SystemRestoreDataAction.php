<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zur System-Wiederherstellung (Restore) aus einem Backup.
 *
 * Path: src/Application/Actions/SystemRestoreDataAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemRestoreDataAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Führt eine System-Wiederherstellung (Restore) aus einem Backup durch.
     * Stellt Daten für das angegebene Ziel aus dem gewählten Zeitstempel wieder her.
     *
     * @param array<string, mixed> $post Formulardaten mit Ziel (target), Zeitstempel (timestamp) und Engine.
     *
     * @return string Statusmeldung über den Erfolg oder Misserfolg des Restores.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.restore.execute')) {
            return 'Fehler: Keine Berechtigung.';
        }

        $dto = SystemMaintenanceRequest::fromArray($post);

        if ($dto->target === '' || $dto->timestamp === '') {
            return 'Fehler: Unvollständige Angaben für Restore.';
        }

        return $this->migrationService->restore($dto->timestamp, $dto->target, $dto->engine);
    }
}
