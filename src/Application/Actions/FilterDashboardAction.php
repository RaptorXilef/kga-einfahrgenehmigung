<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;

/**
 * Action zum Speichern der Dashboard-Filter in der aktuellen Session.
 *
 * Path: src/Application/Actions/FilterDashboardAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class FilterDashboardAction implements ActionInterface
{
    /**
     * Hilfsmethode zum Speichern der Dashboard-Filter in der aktuellen Session.
     *
     * @param array<string, mixed> $post Das POST-Array mit den Filterdaten.
     *
     * @return string Statusmeldung über den Erfolg der Anwendung.
     */
    public function execute(array $post): string
    {
        $_SESSION['admin_filters'] = [
            'end'   => (string) ($post['end'] ?? ''),
            'limit' => (int) ($post['limit'] ?? 25),
            'q'     => (string) ($post['q'] ?? ''),
            'start' => (string) ($post['start'] ?? ''),
            'type'  => (string) ($post['type'] ?? 'all'),
        ];

        return 'Filter angewendet.';
    }
}
