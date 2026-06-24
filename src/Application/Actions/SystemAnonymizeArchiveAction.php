<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;

/**
 * Action zur DSGVO-konformen Anonymisierung von alten Archiv-Einträgen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemAnonymizeArchiveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private PermitArchiveRepositoryInterface $archiveRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.migration.anonymize.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $count = $this->archiveRepository->anonymizeOldRecords(10);
            if ($count === 0) {
                return new RedirectResponse('admin.php?msg=' . \urlencode('Hinweis: Es wurden keine Archiv-Einträge gefunden, die älter als 10 Jahre sind.'));
            }

            return new RedirectResponse('admin.php?msg=' . \urlencode("Erfolg: Es wurden $count alte Archiv-Einträge DSGVO-konform anonymisiert."));
        } catch (\Throwable $e) {
            \error_log('DSGVO Anonymize Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler bei der Anonymisierung: ' . $e->getMessage()));
        }
    }
}
