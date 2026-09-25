<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use Override;
use Throwable;

/**
* Action zum Leeren (Truncate) einer ausgewählten Datenbank-Tabelle.
*/
#[Route('POST', '/truncate_target')]
#[RequiresAuth]
final readonly class TruncateTargetAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private TruncateTargetHandler $truncateHandler,
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.backup.manage';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $target = \trim((string) ($request->post['target'] ?? ''));

        try {
            $tableName = $this->truncateHandler->handle(new TruncateTargetCommand($target));
            $this->auditLogger->log(
                'SYSTEM_TABLE_TRUNCATE',
                "Tabelle '{$tableName}' (Ziel: {$target}) wurde manuell geleert (inkl. Vorab-Backup).",
            );
            $this->sessionManager->addFlash('success', "Tabelle '{$tableName}' wurde gesichert und erfolgreich geleert.");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler beim Leeren der Tabelle: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
