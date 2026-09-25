<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportCommand;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportHandler;
use App\Modules\Finance\Presentation\View\BankImportReportPresenter;
use Override;
use Throwable;

/**
 * Nimmt die hochgeladene Bank-CSV entgegen und leitet je nach Modus zum Zuordnungs-Wizard oder Direkt-Import weiter.
 * VSA FIX: Kein künstlicher ServerRequest und kein Action-zu-Action-Aufruf mehr.
 */
#[Route('POST', '/bank_import_analyze')]
#[RequiresAuth]
final readonly class AnalyzeBankImportAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private AnalyzeBankImportHandler $analyzeHandler,
        private ProcessBankImportHandler $processHandler,
        private BankImportInfrastructureInterface $infrastructure,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'finance.bank_import';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $file = $request->files['bank_csv'] ?? null;
        if (!\is_array($file) || (isset($file['error']) && $file['error'] !== 0)) {
            $this->sessionManager->addFlash('error', 'Fehler beim Datei-Upload.');

            return new RedirectResponse('admin');
        }

        try {
            $tempPath = $this->infrastructure->storeTempFile((string) ($file['tmp_name'] ?? ''));
        } catch (Throwable) {
            $this->sessionManager->addFlash('error', 'Datei konnte nicht verarbeitet werden.');

            return new RedirectResponse('admin');
        }

        $analysis = $this->analyzeHandler->handle(new AnalyzeBankImportQuery($tempPath));

        if ($analysis->headers === []) {
            $this->sessionManager->addFlash('error', 'Die CSV-Datei ist leer oder konnte nicht gelesen werden.');

            return new RedirectResponse('admin');
        }

        if ($this->config->getString('bank_import_mode', 'simple') === 'advanced') {
            $this->sessionManager->setFormData([
                'bank_wizard' => [
                    'headers' => $analysis->headers,
                    'previewRow' => $analysis->previewRow,
                    'tempFile' => $tempPath,
                    'guessId' => $analysis->guessedId,
                    'guessAmount' => $analysis->guessedAmount,
                    'guessDate' => $analysis->guessedDate,
                ],
            ]);

            $this->sessionManager->addFlash('success', 'CSV erfolgreich analysiert. Bitte bestätigen Sie die Spaltenzuordnung.');

            return new RedirectResponse('admin');
        }

        try {
            $result = $this->processHandler->handle(new ProcessBankImportCommand(
                tempFile: $tempPath,
                idColumn: $analysis->guessedId,
                amountColumn: $analysis->guessedAmount,
                dateColumn: $analysis->guessedDate,
            ));

            if ($result->success) {
                foreach ($result->collectiveTransfers as $transfer) {
                    $this->sessionManager->addCollectiveTransfer($transfer);
                }

                $reportHtml = BankImportReportPresenter::formatFlashReport($result, $this->config->getBaseUrl());
                $this->sessionManager->addFlash('success', $reportHtml);
            } else {
                $this->sessionManager->addFlash('error', $result->message);
            }
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-finance');
    }
}
