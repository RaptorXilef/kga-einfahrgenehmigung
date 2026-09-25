<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

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
use Throwable;

/**
 * Leert eine spezifische Datenbank-Tabelle nach vorherigem Sicherheits-Backup.
 */
#[Route('POST', '/truncate_target')]
#[RequiresAuth]
final readonly class TruncateTargetAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private BackupServiceInterface $backupService,
        private AuditLoggerService $auditLogger,
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
        $targetKey = \trim((string) ($request->post['target'] ?? ''));
        $storageConfig = $this->config->getArray('storage_config');

        if ($targetKey === '' || $targetKey === 'all' || !isset($storageConfig[$targetKey]['table'])) {
            $this->sessionManager->addFlash('error', 'Ungültige oder nicht erlaubte Zieltabelle ausgewählt.');

            return new RedirectResponse('admin?focus=tab-backup');
        }

        $tableName = (string) $storageConfig[$targetKey]['table'];

        try {
            // Vor dem Leeren immer ein automatisches Backup dieser Tabelle anfertigen
            $safetyBackup = $this->backupService->createBackup($targetKey);

            $this->pdo->exec("TRUNCATE TABLE `{$tableName}`");

            $this->auditLogger->log(
                'SYSTEM_TABLE_TRUNCATE',
                "Tabelle '{$tableName}' ({$targetKey}) wurde vollständig geleert. Sicherheits-Backup: {$safetyBackup}",
            );
            $this->sessionManager->addFlash('success', "Tabelle '{$tableName}' wurde erfolgreich geleert (Backup '{$safetyBackup}' wurde vorab erstellt).");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler beim Leeren der Tabelle: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
