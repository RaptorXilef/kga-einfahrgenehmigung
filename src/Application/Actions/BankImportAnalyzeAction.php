<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;
use App\Core\Service\BankImportService;

final readonly class BankImportAnalyzeAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthService $auth,
        private BankImportService $importService,
        private TemplateRenderer $renderer,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.finance.bank_import';
    }

    public function execute(ServerRequest $request): mixed
    {
        $file = $request->files['bank_csv'] ?? null;
        if (! $file || (isset($file['error']) && $file['error'] !== 0)) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler beim Datei-Upload.'));
        }

        // Datei an einen sicheren temporären Ort verschieben
        $tempPath = \sys_get_temp_dir() . '/kga_bank_' . \uniqid() . '.csv';
        if (! \move_uploaded_file($file['tmp_name'], $tempPath)) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Datei konnte nicht verarbeitet werden.'));
        }

        $headers = $this->importService->extractHeaders($tempPath);

        // Erste echte Datenzeile für die Live-Vorschau auslesen
        $firstRowData = [];
        if (! empty($headers) && $handle = \fopen($tempPath, 'r')) {
            $firstLine = \fgets($handle);
            \rewind($handle);
            $delimiter = ';';
            if ($firstLine !== false && \substr_count($firstLine, ',') > \substr_count($firstLine, ';')) {
                $delimiter = ',';
            }
            // Header überspringen
            \fgetcsv($handle, 0, $delimiter, '"', '\\');
            // Erste Datenzeile lesen
            $row = \fgetcsv($handle, 0, $delimiter, '"', '\\');
            if ($row !== false) {
                $firstRowData = $row;
            }
            \fclose($handle);
        }

        // Intelligente Vorauswahl treffen basierend auf typischen Bank-Begriffen
        $guessedId     = 4;
        $guessedAmount = 14;
        $guessedDate   = 1;
        foreach ($headers as $index => $header) {
            $h = \strtolower(\trim($header));
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

        // Dashboard rendern und Wizard öffnen - jetzt inkl. bank_row_preview
        $this->renderer->render('admin_dashboard', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'message'         => 'CSV erfolgreich analysiert. Bitte bestätigen Sie die Spaltenzuordnung.',

            // Für den Bank Import Wizard
            'bank_headers'     => $headers,
            'bank_row_preview' => $firstRowData, // Beispieldaten mitgeben
            'bank_temp_file'   => $tempPath,
            'guess_id'         => $guessedId,
            'guess_amount'     => $guessedAmount,
            'guess_date'       => $guessedDate,

            // Fallback-Dummys, damit die restlichen Tabs nicht abstürzen
            'filterStart'       => \date('Y-01-01'),
            'filterEnd'         => \date('Y-12-31'),
            'filterType'        => 'all',
            'currentPage'       => 1,
            'itemsPerPage'      => 25,
            'allowedLimits'     => [10, 25, 50, 100],
            'allPermits'        => [],
            'permitGroups'      => ['active' => [], 'future' => [], 'expired' => [], 'unpaid' => []],
            'yearlyStats'       => [],
            'mailLogs'          => [],
            'vouchers'          => [],
            'voucherArchive'    => [],
            'voucherValidities' => [],
            'overdueLevels'     => [],
            'periodStats'       => ['count' => 0, 'types' => [], 'revenue_paid' => 0, 'revenue_unpaid' => 0],
            'backups'           => [],
            'structure'         => [],
        ]);

        return null;
    }
}
