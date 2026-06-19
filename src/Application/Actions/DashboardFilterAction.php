<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\DashboardFilterRequest;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;

/**
 * Action zum Speichern der Dashboard-Filter in der aktuellen Session.
 *
 * Path: src/Application/Actions/DashboardFilterAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class DashboardFilterAction implements ActionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    /**
     * Hilfsmethode zum Speichern der Dashboard-Filter in der aktuellen Session.
     *
     * @param array<string, mixed> $post Das POST-Array mit den Filterdaten.
     *
     * @return string Statusmeldung über den Erfolg der Anwendung.
     */
    public function execute(array $post): string
    {
        // Wirft keine ValidationException, da Standardwerte greifen
        $dto = DashboardFilterRequest::fromArray($post);

        $this->sessionManager->setAdminFilters([
            'end'   => $dto->end,
            'limit' => $dto->limit,
            'q'     => $dto->q,
            'start' => $dto->start,
            'type'  => $dto->type,
        ]);

        return 'Filter angewendet.';
    }
}
