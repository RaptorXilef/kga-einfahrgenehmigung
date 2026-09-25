<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\Maintenance;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Integration\PermitIntegrationInterface;
use App\Contracts\System\AuditLoggerInterface;
use Override;
use Throwable;

#[Route('POST', '/anonymize_archive')]
#[RequiresAuth]
final readonly class AnonymizeArchiveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private PermitIntegrationInterface $permitIntegration,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.maintenance.execute';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $count = $this->permitIntegration->anonymizeArchive(10);

            if ($count === 0) {
                $this->sessionManager->addFlash('info', 'Hinweis: Es wurden keine Archiv-Einträge gefunden, die älter als 10 Jahre sind.');
            } else {
                $this->auditLogger->log('SYSTEM_ANONYMIZE', "DSGVO-Bereinigung durchgeführt. {$count} alte Archiv-Einträge wurden anonymisiert.");
                $this->sessionManager->addFlash('success', "Erfolg: Es wurden {$count} alte Archiv-Einträge DSGVO-konform anonymisiert.");
            }

            return new RedirectResponse('admin');
        } catch (Throwable $e) {
            \error_log('DSGVO Anonymize Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Fehler bei der Anonymisierung: ' . $e->getMessage());

            return new RedirectResponse('admin');
        }
    }
}
