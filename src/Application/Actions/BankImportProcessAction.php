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
use App\Core\Service\BankImportService;
use Throwable;

#[ActionRoute('bank_import_process')]
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

                // Basis-Nachricht
                $msg = "Bank-Abgleich beendet: <strong>{$erfolgreichCount}</strong> Permits freigeschaltet, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.";

                $htmlDetails = [];
                $logDetails = [];

                // Details: Erfolgreich
                if (!empty($res['erfolgreich_codes']) && \is_array($res['erfolgreich_codes'])) {
                    $codes = \implode(', ', $res['erfolgreich_codes']);
                    $htmlDetails[] = '<br>✅ <b>Erfolgreich:</b> ' . \htmlspecialchars($codes);
                    $logDetails[] = 'Erfolgreich: [' . $codes . ']';
                }

                // Details: Übersprungen
                if (!empty($res['uebersprungen_codes']) && \is_array($res['uebersprungen_codes'])) {
                    $codes = \implode(', ', $res['uebersprungen_codes']);
                    $htmlDetails[] = '<br>⏭️ <b>Übersprungen:</b> ' . \htmlspecialchars($codes);
                    $logDetails[] = 'Übersprungen: [' . $codes . ']';
                }

                // Details: Fehlerhaft
                if (!empty($res['fehlerhaft_codes']) && \is_array($res['fehlerhaft_codes'])) {
                    $codes = \implode(', ', $res['fehlerhaft_codes']);
                    $htmlDetails[] = '<br>❌ <b>Fehlerhaft (Summe zu gering):</b> ' . \htmlspecialchars($codes);
                    $logDetails[] = 'Fehlerhaft: [' . $codes . ']';
                }

                // Details: Unlesbare Zeilen (Formatierungsfehler in der CSV)
                if (($res['unbekannte_fehler'] ?? 0) > 0) {
                    $htmlDetails[] = '<br>⚠️ <b>Unlesbare Zeilen (z.B. falsches CSV-Format):</b> ' . $res['unbekannte_fehler'];
                    $logDetails[] = 'Unlesbare Zeilen: ' . $res['unbekannte_fehler'];
                }

                // Zusammenbauen für die grüne Toast/Flash-Nachricht
                $fullMsg = $msg . \implode('', $htmlDetails);

                // Zusammenbauen für das Nutzerprotokoll (Audit Log)
                $logStr = "CSV-Import abgeschlossen: {$erfolgreichCount} erfolgreich, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.";
                if ($logDetails !== []) {
                    $logStr .= ' | Details -> ' . \implode(' | ', $logDetails);
                }

                // LOG SCHREIBEN
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
