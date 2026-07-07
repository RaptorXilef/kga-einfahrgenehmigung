<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\BankImportProcessRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\BankImportService; // <--- NEU

#[ActionRoute('bank_import_process')]
final readonly class BankImportProcessAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger, // <--- NEU
        private BankImportService $importService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.finance.bank_import';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = BankImportProcessRequest::fromArray($request->post);
            $res = $this->importService->processCsv($dto->tempFile, $dto->idColumn, $dto->amountColumn, $dto->dateColumn);

            if (\file_exists($dto->tempFile)) {
                @\unlink($dto->tempFile);
            }

            if (($res['success'] ?? false) === true) {
                $msg = "Bank-Abgleich beendet: <strong>{$res['erfolgreich']}</strong> Permits freigeschaltet, {$res['uebersprungen']} übersprungen, {$res['fehlerhaft']} fehlerhaft.";

                // LOG SCHREIBEN
                $this->auditLogger->log('BANK_IMPORT', "CSV-Import abgeschlossen: {$res['erfolgreich']} erfolgreich, {$res['uebersprungen']} übersprungen, {$res['fehlerhaft']} fehlerhaft.");

                $this->sessionManager->addFlash('success', $msg);
            } else {
                $this->sessionManager->addFlash('error', 'Fehler bei der CSV-Verarbeitung.');
            }

            return new RedirectResponse('admin.php');

        } catch (\Exception $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
