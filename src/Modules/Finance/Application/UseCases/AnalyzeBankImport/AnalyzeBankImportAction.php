<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportAction;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportHandler;
use Override;
use Throwable;

#[Route('GET', '/bank_import_analyze')]
#[Route('POST', '/bank_import_analyze')]
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
        if (!$file || (isset($file['error']) && $file['error'] !== 0)) {
            $this->sessionManager->addFlash('error', 'Fehler beim Datei-Upload.');

            return new RedirectResponse('admin');
        }

        try {
            $tempPath = $this->infrastructure->storeTempFile($file['tmp_name']);
        } catch (Throwable) {
            $this->sessionManager->addFlash('error', 'Datei konnte nicht verarbeitet werden.');

            return new RedirectResponse('admin');
        }

        $analysis = $this->analyzeHandler->handle(new AnalyzeBankImportQuery($tempPath));
        $headers = $analysis->headers;

        if ($headers === []) {
            $this->sessionManager->addFlash('error', 'Die CSV-Datei ist leer oder konnte nicht gelesen werden.');

            return new RedirectResponse('admin');
        }

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

        $mode = $this->config->getString('bank_import_mode', 'simple');

        if ($mode === 'advanced') {
            $this->sessionManager->setFormData([
                'bank_wizard' => [
                    'headers' => $headers,
                    'previewRow' => $analysis->previewRow,
                    'tempFile' => $tempPath,
                    'guessId' => $guessedId,
                    'guessAmount' => $guessedAmount,
                    'guessDate' => $guessedDate,
                ],
            ]);

            $this->sessionManager->addFlash('success', 'CSV erfolgreich analysiert. Bitte bestätigen Sie die Spaltenzuordnung.');

            return new RedirectResponse('admin');
        }

        // Delegierung im Simple Mode auf die neue VSA Action
        $processAction = new ProcessBankImportAction($this->sessionManager, $this->config, $this->processHandler);

        $simulatedPost = \array_merge($request->post, [
            'temp_file' => $tempPath,
            'col_id' => $guessedId,
            'col_amount' => $guessedAmount,
            'col_date' => $guessedDate,
        ]);

        $simulatedRequest = new ServerRequest(
            get: $request->get,
            post: $simulatedPost,
            files: $request->files,
            server: $request->server,
            input: $simulatedPost,
            cookie: $request->cookie,
        );

        return $processAction->execute($simulatedRequest);
    }
}
