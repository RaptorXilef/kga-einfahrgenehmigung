<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\BankImportProcessRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\BankImportService;
use Throwable;

#[Route('POST', '/bank_import_process')]
#[RequiresAuth]
final readonly class BankImportProcessAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
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
                $erfolgreichCount = (int) ($res['erfolgreich_count'] ?? 0);
                $uebersprungenCount = (int) ($res['uebersprungen_count'] ?? 0);
                $fehlerhaftCount = (int) ($res['fehlerhaft_count'] ?? 0);

                $msg = "Bank-Abgleich beendet: <strong>{$erfolgreichCount}</strong> Permits freigeschaltet, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.";
                $htmlDetails = [];
                $logDetails = [];

                // 1. Erfolgreich freigeschaltete Genehmigungen
                if (!empty($res['erfolgreich_details']) && \is_array($res['erfolgreich_details'])) {
                    $htmlDetails[] = '<div style="margin-top: 6px;">✅ <strong>Freigeschaltet:</strong> ' . \htmlspecialchars(\implode(', ', $res['erfolgreich_details'])) . '</div>';
                    $logDetails[] = 'Freigeschaltet: [' . \implode(', ', $res['erfolgreich_details']) . ']';
                }

                // 2. Übersprungene Datensätze
                if (!empty($res['uebersprungen_details']) && \is_array($res['uebersprungen_details'])) {
                    $htmlDetails[] = '<div style="margin-top: 4px;">⏭️ <strong>Übersprungen:</strong> ' . \htmlspecialchars(\implode(', ', $res['uebersprungen_details'])) . '</div>';
                    $logDetails[] = 'Übersprungen: [' . \implode(', ', $res['uebersprungen_details']) . ']';
                }

                // 3. Fehlerhafte Datensätze
                if (!empty($res['fehlerhaft_details']) && \is_array($res['fehlerhaft_details'])) {
                    $htmlDetails[] = '<div style="margin-top: 4px;">❌ <strong>Fehlerhaft:</strong> ' . \htmlspecialchars(\implode(', ', $res['fehlerhaft_details'])) . '</div>';
                    $logDetails[] = 'Fehlerhaft: [' . \implode(', ', $res['fehlerhaft_details']) . ']';
                }

                // 4. Formatierungsfehler in der CSV
                if (!empty($res['unlesbare_zeilen_details']) && \is_array($res['unlesbare_zeilen_details'])) {
                    $htmlDetails[] = '<div style="margin-top: 4px;">⚠️ <strong>CSV-Fehler:</strong> ' . \htmlspecialchars(\implode(', ', $res['unlesbare_zeilen_details'])) . '</div>';
                    $logDetails[] = 'CSV-Fehler: [' . \implode(', ', $res['unlesbare_zeilen_details']) . ']';
                }

                $fullMsg = $msg . \implode('', $htmlDetails);
                $logStr = "CSV-Import abgeschlossen: {$erfolgreichCount} erfolgreich, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.";
                if ($logDetails !== []) {
                    $logStr .= ' | ' . \implode(' | ', $logDetails);
                }

                $this->auditLogger->log('BANK_IMPORT', $logStr);
                $this->sessionManager->addFlash('success', $fullMsg);
            } else {
                $this->sessionManager->addFlash('error', (string) ($res['message'] ?? 'Fehler bei der CSV-Verarbeitung.'));
            }

            return new RedirectResponse('admin.php');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
