<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitService;

/**
 * Action zum manuellen Markieren einer Genehmigung als 'bezahlt'.
 *
 * Path: src/Application/Actions/MarkAsPaidAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MarkAsPaidAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private PermitService $permitService,
    ) {
    }

    /**
     * Markiert eine Genehmigung manuell als bezahlt im Storage.
     *
     * Nutzt PermitService::manualActivate().
     *
     * @param array<string, mixed> $post
     *
     * @return string Erfolgsmeldung oder leerer String bei Fehler.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.finance.mark_paid')) {
            return 'Fehler: Keine Berechtigung für diese Aktion.';
        }

        $code = (string) ($post['code'] ?? '');

        return $this->permitService->manualActivate($code) ? "Zahlung für $code bestätigt." : '';
    }
}
