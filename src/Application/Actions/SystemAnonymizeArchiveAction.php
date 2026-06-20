<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;

/**
 * Action zur DSGVO-konformen Anonymisierung von alten Archiv-Einträgen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemAnonymizeArchiveAction implements ActionInterface
{
    public function __construct(
        private PermitArchiveRepositoryInterface $archiveRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
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
