<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zum Leeren des Anwendungs-Caches.
 *
 * Path: src/Application/Actions/SystemClearCacheAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemClearCacheAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Leert den Anwendungs-Cache und löscht temporäre System-Dateien.
     *
     * @param array<string, mixed> $post Formulardaten inklusive CSRF-Token.
     *
     * @return string Statusmeldung über die Ausführung.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.delete-cache.execute')) {
            return 'Fehler: Sie haben keine Berechtigung für diese Aktion.';
        }

        return $this->migrationService->clearCache();
    }
}
