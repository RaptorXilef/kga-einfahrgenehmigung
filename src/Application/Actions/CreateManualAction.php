<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitService;

/**
 * Action zur manuellen Ausstellung einer Genehmigung (ohne Zahlungsfluss).
 *
 * Path: src/Application/Actions/CreateManualAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CreateManualAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private PermitService $permitService,
    ) {
    }

    /**
     * Erstellt eine Genehmigung ohne vorangegangenen automatisierten Bezahlprozess.
     *
     * Erzwingt 'status' = 'bezahlt' und nutzt PermitService::createPermit().
     *
     * @param array<string, mixed> $post
     *
     * @return string Bestätigung mit dem generierten Genehmigungscode.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.direct_issue.execute')) {
            return 'Fehler: Sie haben keine Berechtigung für manuelle Ausstellungen.';
        }

        $tplKey = (string) ($post['template_key'] ?? 'std.7');

        // --- BACKEND SECURITY CHECK ---
        if (! $this->auth->hasPermission("template.$tplKey")) {
            return "Fehler: Sie haben keine Berechtigung, den Typ '$tplKey' manuell auszustellen.";
        }

        // Manuelle Buchung (Kostenlos/Bar)
        try {
            $post['status'] = 'bezahlt';
            if (isset($post['reason'])) {
                $post['interner_kommentar'] = $post['reason'];
            }

            $permit = $this->permitService->createPermit($post, true);

            return "Manuelle Genehmigung erstellt: <strong>{$permit->code}</strong>";
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
