<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\BankImportService;

#[Route('GET', '/bank_import_analyze')]
#[Route('POST', '/bank_import_analyze')]
final readonly class BankImportAnalyzeAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private ConfigInterface $config,
        private BankImportService $importService,
        private SessionManager $sessionManager,
    ) {}

    public function getRequiredPermission(): string
    {
        return 'finance.bank_import';
    }

    public function execute(ServerRequest $request): mixed
    {
        $file = $request->files['bank_csv'] ?? null;
        if (!$file || (isset($file['error']) && $file['error'] !== 0)) {
            $this->sessionManager->addFlash('error', 'Fehler beim Datei-Upload.');

            return new RedirectResponse('admin.php');
        }

        $tempPath = \sys_get_temp_dir() . '/kga_bank_' . \uniqid('', true) . '.csv';
        if (!\move_uploaded_file($file['tmp_name'], $tempPath)) {
            $this->sessionManager->addFlash('error', 'Datei konnte nicht verarbeitet werden.');

            return new RedirectResponse('admin.php');
        }

        $analysis = $this->importService->analyzeCsv($tempPath);
        $headers = $analysis['headers'];

        if (empty($headers)) {
            $this->sessionManager->addFlash('error', 'Die CSV-Datei ist leer oder konnte nicht gelesen werden.');

            return new RedirectResponse('admin.php');
        }

        // Heuristik: Spalten automatisch erraten
        $guessedId = 4;
        $guessedAmount = 14;
        $guessedDate = 1;

        foreach ($headers as $index => $header) {
            $h = \strtolower(\trim((string) $header));
            if (\str_contains($h, 'zweck') || \str_contains($h, 'remittance')) {
                $guessedId = $index;
            }
            if (\str_contains($h, 'betrag') || \str_contains($h, 'amount')) {
                $guessedAmount = $index;
            }
            if (!\str_contains($h, 'buchungstag') && !\str_contains($h, 'valuta') && !\str_contains($h, 'date')) {
                continue;
            }
            $guessedDate = $index;
        }

        $mode = $this->config->get('bank_import_mode', 'simple');

        // --- ADVANCED MODUS --- (Zeigt die Spalten-Auswahl an)
        if ($mode === 'advanced') {
            $this->sessionManager->setFormData([
                'bank_wizard' => [
                    'headers' => $headers,
                    'previewRow' => $analysis['previewRow'],
                    'tempFile' => $tempPath,
                    'guessId' => $guessedId,
                    'guessAmount' => $guessedAmount,
                    'guessDate' => $guessedDate,
                ],
            ]);

            $this->sessionManager->addFlash('success', 'CSV erfolgreich analysiert. Bitte bestätigen Sie die Spaltenzuordnung.');

            return new RedirectResponse('admin.php');
        }

        // --- SIMPLE MODUS --- (Führt den Import direkt aus)
        $res = $this->importService->processCsv($tempPath, $guessedId, $guessedAmount, $guessedDate);

        if (\file_exists($tempPath)) {
            @\unlink($tempPath);
        }

        if (($res['success'] ?? false) === true) {
            $erfolgreichCount = (int) ($res['erfolgreich_count'] ?? 0);
            $uebersprungenCount = (int) ($res['uebersprungen_count'] ?? 0);
            $fehlerhaftCount = (int) ($res['fehlerhaft_count'] ?? 0);

            $msg = "Bank-Abgleich beendet: <strong>{$erfolgreichCount}</strong> Permits freigeschaltet, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.";
            $htmlDetails = [];
            $logDetails = [];

            if (!empty($res['erfolgreich_details']) && \is_array($res['erfolgreich_details'])) {
                $htmlDetails[] = '<div style="margin-top: 6px;">✅ <strong>Freigeschaltet:</strong> ' . \htmlspecialchars(\implode(', ', $res['erfolgreich_details'])) . '</div>';
                $logDetails[] = 'Freigeschaltet: [' . \implode(', ', $res['erfolgreich_details']) . ']';
            }
            if (!empty($res['uebersprungen_details']) && \is_array($res['uebersprungen_details'])) {
                $htmlDetails[] = '<div style="margin-top: 4px;">⏭️ <strong>Übersprungen:</strong> ' . \htmlspecialchars(\implode(', ', $res['uebersprungen_details'])) . '</div>';
                $logDetails[] = 'Übersprungen: [' . \implode(', ', $res['uebersprungen_details']) . ']';
            }
            if (!empty($res['fehlerhaft_details']) && \is_array($res['fehlerhaft_details'])) {
                $htmlDetails[] = '<div style="margin-top: 4px;">❌ <strong>Fehlerhaft:</strong> ' . \htmlspecialchars(\implode(', ', $res['fehlerhaft_details'])) . '</div>';
                $logDetails[] = 'Fehlerhaft: [' . \implode(', ', $res['fehlerhaft_details']) . ']';
            }
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

        // Springt nach dem automatischen Import direkt ins Finanz-Tab zurück
        return new RedirectResponse('admin.php?focus=tab-finance');
    }
}
