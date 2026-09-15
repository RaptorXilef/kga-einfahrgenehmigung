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
use App\Contracts\Config\ConfigInterface;
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
        private ConfigInterface $config,
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
                    foreach ($res['sammel_transfers'] as $transfer) {
                        $this->sessionManager->addCollectiveTransfer($transfer);
                    }
                }

                $baseUrl = $this->config->getBaseUrl();

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

                // Audit Log (Flach)
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

                $logDetails = [];
                if (!empty($res['erfolgreich_details'])) {
                    $logDetails[] = 'Freigeschaltet: [' . $flattenForLog($res['erfolgreich_details']) . ']';
                }
                if (!empty($res['uebersprungen_details'])) {
                    $logDetails[] = 'Übersprungen: [' . $flattenForLog($res['uebersprungen_details']) . ']';
                }
                if (!empty($fehlerhaftDetails)) {
                    $logDetails[] = 'Fehlerhaft: [' . $flattenForLog($fehlerhaftDetails) . ']';
                }

                $logStr = "CSV-Import abgeschlossen: {$erfolgreichCount} erfolgreich, {$uebersprungenCount} übersprungen, {$fehlerhaftCount} fehlerhaft.";
                if ($logDetails !== []) {
                    $logStr .= ' | ' . \implode(' | ', $logDetails);
                }

                $this->auditLogger->log('BANK_IMPORT', $logStr);
                $this->sessionManager->addFlash('success', $fullMsg);
            } else {
                $this->sessionManager->addFlash('error', (string) ($res['message'] ?? 'Fehler bei der CSV-Verarbeitung.'));
            }

            // Bei Fehler auch dorthin zurückspringen, wo der User gestartet ist
            return new RedirectResponse('admin?focus=tab-finance');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            // Zwingendes Return hinzugefügt, um Interface-Vorgaben zu erfüllen!
            return new RedirectResponse('admin?focus=tab-finance');
        }
    }
}
