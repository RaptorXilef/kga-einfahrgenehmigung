<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\BankImportService;

#[ActionRoute('bank_import_analyze')]
final readonly class BankImportAnalyzeAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
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

        // Korrektur: Anstatt das Dashboard isoliert und fehlerhaft zu rendern,
        // übergeben wir den Status via Session an das ohnehin fehlerfrei ladende Dashboard.
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
}
