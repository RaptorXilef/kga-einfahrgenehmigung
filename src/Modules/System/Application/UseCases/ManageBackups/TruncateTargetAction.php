<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use Override;
use PDO;
use Throwable;

#[Route('GET', '/truncate_target')]
#[Route('POST', '/truncate_target')]
final readonly class TruncateTargetAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
        private PDO $pdo,
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
        try {
            $dto = TruncateTargetRequest::fromArray($request->post);
            $target = $dto->target;

            // 1. ZWANGS-VOLL-BACKUP
            $this->backupService->createBackup('all');

            // 2. Tabellen-Namen sicher aus der Config ermitteln
            $cfg = $this->config->getArray('storage_config')[$target] ?? null;
            if (!$cfg) {
                $this->sessionManager->addFlash('error', "Fehler: Unbekannter Speicherbereich '$target'.");

                return new RedirectResponse('admin?focus=tab-backup');
            }

            $tableName = $cfg['table'];
            $allowedTables = \array_column($this->config->getArray('storage_config'), 'table');

            if (!\in_array($tableName, $allowedTables, true)) {
                $this->sessionManager->addFlash('error', 'Sicherheitsabbruch: Tabellenname nicht autorisiert.');

                return new RedirectResponse('admin?focus=tab-backup');
            }

            // 3. Tabelle restlos leeren
            $this->pdo->exec("TRUNCATE TABLE `$tableName`");

            $this->auditLogger->log('SYSTEM_TRUNCATE', "Sicherheitslöschung (TRUNCATE) durchgeführt. Tabelle: {$tableName}.");
            $this->sessionManager->addFlash('success', "Erfolg: Die Tabelle '{$tableName}' wurde restlos geleert. Ein Voll-Backup wurde vorab erstellt.");

            return new RedirectResponse('admin?focus=tab-backup');
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin?focus=tab-backup');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler beim Leeren der Tabelle: ' . $e->getMessage());

            return new RedirectResponse('admin?focus=tab-backup');
        }
    }
}
