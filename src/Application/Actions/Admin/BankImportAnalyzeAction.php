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
use App\Modules\Finance\Application\UseCases\AnalyzeBankImport\AnalyzeBankImportHandler;
use App\Modules\Finance\Application\UseCases\AnalyzeBankImport\AnalyzeBankImportQuery;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportHandler;

#[Route('GET', '/bank_import_analyze')]
#[Route('POST', '/bank_import_analyze')]
final readonly class BankImportAnalyzeAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private AnalyzeBankImportHandler $analyzeHandler, // CQRS
        private ProcessBankImportHandler $processHandler, // CQRS
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

        $analysis = $this->analyzeHandler->handle(new AnalyzeBankImportQuery($tempPath));
        $headers = $analysis->headers;

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
            if (\str_contains($h, 'buchungstag') || \str_contains($h, 'valuta') || \str_contains($h, 'date')) {
                $guessedDate = $index;
            }
        }

        $mode = $this->config->get('bank_import_mode', 'simple');

        // --- ADVANCED MODUS --- (Zeigt die Spalten-Auswahl an)
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

        // --- SIMPLE MODUS --- (Führt den Import direkt aus)
        // Im Simple Mode delegieren wir den Request direkt an den Process Handler und rufen die Helper Action auf.
        $processAction = new BankImportProcessAction($this->sessionManager, $this->config, $this->processHandler);

        // Simuliere einen Request mit den geratenen Spalten (Als sauberes, neues Readonly-Objekt)
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
