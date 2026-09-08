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
        return 'finance.bank_import';
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

                // Sammelüberweisungen aus dem Service in die Session schieben
                $fehlerhaftDetails = $res['fehlerhaft_details'] ?? [];
                if (!empty($res['sammel_transfers'])) {
                    $sammelList = [];
                    $kennzeichenList = [];
                    foreach ($res['sammel_transfers'] as $transfer) {
                        $this->sessionManager->addCollectiveTransfer($transfer);
                        $entry = \implode(', ', $transfer['codes']) . ' (' . \number_format($transfer['amount'], 2, ',', '.') . ' €)';
                        if (($transfer['type'] ?? 'sammel') === 'kennzeichen') {
                            $kennzeichenList[] = $entry;
                        } else {
                            $sammelList[] = $entry;
                        }
                    }
                    if (!empty($sammelList)) {
                        $fehlerhaftDetails['Sammelüberweisungen (Manuell prüfen)'] = $sammelList;
                    }
                    if (!empty($kennzeichenList)) {
                        $fehlerhaftDetails['Kennzeichen erkannt (Manuell prüfen)'] = $kennzeichenList;
                    }
                }

                // Doppelter Zeilenumbruch für saubere Trennung vom Hauptsatz
                $msg = "Bank-Abgleich beendet: <strong>{$erfolgreichCount}</strong> Permits freigeschaltet, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.<br><br>";
                $htmlDetails = [];
                $logDetails = [];

                // Formatiert Arrays mit Kategorien sauber als strukturierte HTML-Liste
                $formatList = function (array $categories): string {
                    $html = '';
                    foreach ($categories as $cat => $items) {
                        if (\is_numeric($cat)) {
                            // Flache Liste (z.B. bei Erfolgreich)
                            $html .= '<br>&nbsp;&nbsp;&bull; ' . \htmlspecialchars((string) $items);
                        } else {
                            // Kategorisierte Liste (z.B. "Fehlt auf Auszug") - FETT und KURSIV
                            $html .= '<br>&nbsp;&nbsp;&bull; <strong><em>' . \htmlspecialchars((string) $cat) . '</em></strong>:';
                            foreach ((array) $items as $item) {
                                $html .= '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- ' . \htmlspecialchars((string) $item);
                            }
                        }
                    }

                    return $html;
                };

                // Flache Darstellung für die Log-Einträge ohne HTML-Tags
                $flattenForLog = function (array $categories): string {
                    $parts = [];
                    foreach ($categories as $cat => $items) {
                        if (\is_numeric($cat)) {
                            $parts[] = (string) $items;
                        } else {
                            $parts[] = $cat . ': ' . \implode(', ', (array) $items);
                        }
                    }

                    return \implode(' | ', $parts);
                };

                // 2. Übersprungene Datensätze
                if (!empty($res['erfolgreich_details'])) {
                    $htmlDetails[] = '<div style="margin-bottom: 12px;">✅ <strong>Freigeschaltet:</strong>' . $formatList($res['erfolgreich_details']) . '</div>';
                    $logDetails[] = 'Freigeschaltet: [' . $flattenForLog($res['erfolgreich_details']) . ']';
                }

                // 3. Fehlerhafte Datensätze
                if (!empty($res['uebersprungen_details'])) {
                    $htmlDetails[] = '<div style="margin-bottom: 12px;">⏭️ <strong>Übersprungen:</strong>' . $formatList($res['uebersprungen_details']) . '</div>';
                    $logDetails[] = 'Übersprungen: [' . $flattenForLog($res['uebersprungen_details']) . ']';
                }

                // 4. Formatierungsfehler in der CSV
                if (!empty($fehlerhaftDetails)) {
                    $htmlDetails[] = '<div style="margin-bottom: 12px;">❌ <strong>Fehlerhaft / Prüfen:</strong>' . $formatList($fehlerhaftDetails) . '</div>';
                    $logDetails[] = 'Fehlerhaft: [' . $flattenForLog($fehlerhaftDetails) . ']';
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

            // Direkt zum Finanzen-Tab springen
            return new RedirectResponse('admin?focus=tab-finance');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            // Bei Fehler auch dorthin zurückspringen, wo der User gestartet ist
            return new RedirectResponse('admin?focus=tab-finance');
        }
    }
}
