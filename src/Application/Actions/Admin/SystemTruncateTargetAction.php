<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Core\Service\AuditLoggerService;
use PDO;
use Throwable;

/**
 * Action zum rigorosen Löschen aller Daten eines bestimmten Speicher-Ziels.
 */
#[Route('GET', '/truncate_target')]
#[Route('POST', '/truncate_target')]
final readonly class SystemTruncateTargetAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
        private PDO $pdo,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.backup.manage';
    }

    /**
     * Löscht alle Daten eines bestimmten Speicher-Ziels rigoros (Truncate).
     * Wird für administrative System-Resets oder vor großen Migrationen verwendet.
     *
     * @return string Statusmeldung über die Löschung.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SystemMaintenanceRequest::forTruncate($request->post);
            $target = $dto->target;

            // 1. ZWANGS-VOLL-BACKUP
            $this->backupService->createBackup('all');

            // 2. Tabellen-Namen sicher aus der Config ermitteln
            $cfg = $this->config->get('storage_config')[$target] ?? null;
            if (!$cfg) {
                $this->sessionManager->addFlash('error', "Fehler: Unbekannter Speicherbereich '$target'.");

                return new RedirectResponse('admin?focus=tab-backup');
            }

            $tableName = $cfg['table'];
            $allowedTables = \array_column($this->config->get('storage_config'), 'table');

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
