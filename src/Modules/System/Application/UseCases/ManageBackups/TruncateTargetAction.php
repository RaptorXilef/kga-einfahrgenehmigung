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
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use Override;
use PDO;
use RuntimeException;
use Throwable;

#[Route('POST', '/truncate_target')]
#[RequiresAuth]
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
        $target = \trim((string) ($request->post['target'] ?? ''));
        $storageConfig = $this->config->getArray('storage_config');

        if ($target === '' || $target === 'all' || !isset($storageConfig[$target]['table'])) {
            $this->sessionManager->addFlash('error', 'Fehler: Ungültige Zieltabelle ausgewählt.');

            return new RedirectResponse('admin?focus=tab-backup');
        }

        $table = (string) $storageConfig[$target]['table'];

        try {
            if (!\preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                throw new RuntimeException('Ungültiger Tabellenname.');
            }

            // Sicherheits-Backup der Tabelle vor dem Leeren erstellen
            $this->backupService->createBackup($target);
            $this->pdo->exec("TRUNCATE TABLE `{$table}`");

            $this->auditLogger->log('SYSTEM_TABLE_TRUNCATE', "Tabelle '{$table}' wurde manuell geleert (Sicherheits-Backup erstellt).");
            $this->sessionManager->addFlash('success', "Tabelle '{$table}' wurde erfolgreich geleert.");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler beim Leeren der Tabelle: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
