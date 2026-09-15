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
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'finance.bank_import';
    }

    public function execute(ServerRequest $request): mixed
    {
        $file = $request->files['bank_csv'] ?? null;
        if (!$file || (isset($file['error']) && $file['error'] !== 0)) {
            $this->sessionManager->addFlash('error', 'Fehler beim Datei-Upload.');

            return new RedirectResponse('admin');
        }

        $tempPath = \sys_get_temp_dir() . '/kga_bank_' . \uniqid('', true) . '.csv';
        if (!\move_uploaded_file($file['tmp_name'], $tempPath)) {
            $this->sessionManager->addFlash('error', 'Datei konnte nicht verarbeitet werden.');

            return new RedirectResponse('admin');
        }

        $analysis = $this->importService->analyzeCsv($tempPath);
        $headers = $analysis['headers'];

        if (empty($headers)) {
            $this->sessionManager->addFlash('error', 'Die CSV-Datei ist leer oder konnte nicht gelesen werden.');

            return new RedirectResponse('admin');
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

            return new RedirectResponse('admin');
        }

        // --- SIMPLE MODUS --- (Führt den Import direkt aus)
        $res = $this->importService->processCsv($tempPath, $guessedId, $guessedAmount, $guessedDate);

        if (($res['success'] ?? false) === true) {
            $baseUrl = $this->config->getBaseUrl(); // Icon-URL Prefix

            // Formatiert Arrays mit Kategorien sauber als strukturierte HTML-Liste
            $formatList = function (array $categories): string {
                $html = '<ul class="u-margin-block-xs u-padding-inline-start-m">';
                foreach ($categories as $cat => $items) {
                    if (\is_numeric($cat)) {
                        $html .= '<li>' . \htmlspecialchars((string) $items) . '</li>';
                    } else {
                        $html .= '<li class="u-margin-block-end-xs"><strong class="u-font-bold"><em>' . \htmlspecialchars((string) $cat) . '</em></strong>:';
                        $html .= '<ul class="u-margin-block-start-none u-margin-block-end-xs u-padding-inline-start-m">';
                        foreach ((array) $items as $item) {
                            $html .= '<li>' . \htmlspecialchars((string) $item) . '</li>';
                        }
                        $html .= '</ul></li>';
                    }
                }
                $html .= '</ul>';

                return $html;
            };

            $htmlDetails = [];

            // Emojis durch <img src="...webp"> ersetzt
            if (!empty($res['erfolgreich_details'])) {
                $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $baseUrl . 'assets/img/icons/status-success.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Freigeschaltet:</strong>' . $formatList($res['erfolgreich_details']) . '</div>';
            }

            if (!empty($res['uebersprungen_details'])) {
                $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $baseUrl . 'assets/img/icons/icon-skip.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Übersprungen:</strong>' . $formatList($res['uebersprungen_details']) . '</div>';
            }

            if (!empty($fehlerhaftDetails)) {
                $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $baseUrl . 'assets/img/icons/status-invalid.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Fehlerhaft / Prüfen:</strong>' . $formatList($fehlerhaftDetails) . '</div>';
            }

            $msg = "<div class=\"u-margin-bottom-m\">Bank-Abgleich beendet: <strong>{$erfolgreichCount}</strong> Permits freigeschaltet, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.</div>";
            $fullMsg = $msg . \implode('', $htmlDetails);

            $this->sessionManager->addFlash('success', $fullMsg);
        } else {
            $this->sessionManager->addFlash('error', (string) ($res['message'] ?? 'Fehler bei der CSV-Verarbeitung.'));
        }

        return new RedirectResponse('admin?focus=tab-finance');
    }
}
