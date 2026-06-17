<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zum rigorosen Löschen aller Daten eines bestimmten Speicher-Ziels.
 *
 * Path: src/Application/Actions/SystemTruncateTargetAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemTruncateTargetAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Löscht alle Daten eines bestimmten Speicher-Ziels rigoros (Truncate).
     * Wird für administrative System-Resets oder vor großen Migrationen verwendet.
     *
     * @param array<string, mixed> $post Formulardaten mit Zielbereich (target) und Speicher-Engine (engine).
     *
     * @return string Statusmeldung über die Löschung.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.delete-data.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, Datenbestände zu löschen.';
        }

        $target = (string) ($post['target'] ?? '');
        $engine = (string) ($post['engine'] ?? 'all');

        if ($target === '') {
            return 'Fehler: Kein Zielbereich ausgewählt.';
        }

        return $this->migrationService->truncateTarget($target, $engine);
    }
}
