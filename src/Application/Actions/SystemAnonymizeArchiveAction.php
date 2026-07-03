<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Core\Service\AuditLoggerService;

/**
 * Action zur DSGVO-konformen Anonymisierung von alten Archiv-Einträgen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('anonymize_archive')]
final readonly class SystemAnonymizeArchiveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private SessionManager $sessionManager,
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
                $this->sessionManager->addFlash('info', 'Hinweis: Es wurden keine Archiv-Einträge gefunden, die älter als 10 Jahre sind.');
            } else {
                $this->auditLogger->log('SYSTEM_ANONYMIZE', "DSGVO-Bereinigung durchgeführt. {$count} alte Archiv-Einträge wurden anonymisiert.");
                $this->sessionManager->addFlash('success', "Erfolg: Es wurden $count alte Archiv-Einträge DSGVO-konform anonymisiert.");
            }

            return new RedirectResponse('admin.php');
        } catch (\Throwable $e) {
            \error_log('DSGVO Anonymize Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Fehler bei der Anonymisierung: ' . $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
