<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action zur DSGVO-konformen Anonymisierung von alten Archiv-Einträgen.
 *
 * Path: src/Application/Actions/SystemAnonymizeArchiveAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemAnonymizeArchiveAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private PermitArchiveRepositoryInterface $archiveRepository,
    ) {
    }

    /**
     * Führt die DSGVO-konforme Anonymisierung von alten Archiv-Einträgen durch.
     *
     * @param array<string, mixed> $post Das POST-Array der Anfrage.
     *
     * @return string Status- oder Erfolgsmeldung über die Anzahl anonymisierter Einträge.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.anonymize.execute')) {
            return 'Fehler: Sie haben keine Berechtigung für die DSGVO-Anonymisierung.';
        }

        try {
            // 10 Jahre gesetzliche Aufbewahrungsfrist
            $count = $this->archiveRepository->anonymizeOldRecords(10);

            if ($count === 0) {
                return 'Hinweis: Es wurden keine Archiv-Einträge gefunden, die älter als 10 Jahre sind.';
            }

            return "Erfolg: Es wurden $count alte Archiv-Einträge DSGVO-konform anonymisiert.";
        } catch (\Exception $e) {
            return 'Fehler bei der Anonymisierung: ' . $e->getMessage();
        }
    }
}
