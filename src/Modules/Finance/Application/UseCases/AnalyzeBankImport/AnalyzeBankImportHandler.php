<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use Exception;
use League\Csv\Reader;
use Override;

/**
 * Liest die Kopfzeile sowie erste Datenzeile einer Bank-CSV und erkennt automatisch die relevanten Spalten.
 *
 * @implements QueryHandlerInterface<AnalyzeBankImportQuery, BankImportAnalysisDto>
 */
final readonly class AnalyzeBankImportHandler implements QueryHandlerInterface
{
    public function __construct(
        private BankImportInfrastructureInterface $infrastructure,
    ) {
    }

    /**
     * @param AnalyzeBankImportQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): BankImportAnalysisDto
    {
        $csv = $this->infrastructure->normalizeAndOpenCsv($query->tempFilePath);

        if (!$csv instanceof Reader) {
            return new BankImportAnalysisDto([], []);
        }

        try {
            $iterator = $csv->getIterator();
            $iterator->rewind();

            $headers = $iterator->valid() && \is_array($iterator->current()) ? $iterator->current() : [];
            $iterator->next();
            $previewRow = $iterator->valid() && \is_array($iterator->current()) ? $iterator->current() : [];

            $guessedId = 4;       // Col 5: Verwendungszweck / Betreff
            $guessedAmount = 14;  // Col 15: Betrag
            $guessedDate = 1;     // Col 2: Buchungstag
            $guessedSender = 11;  // Col 12: Name des Überweisenden
            $guessedCurrency = 15; // Col 16: Währung

            foreach ($headers as $index => $header) {
                $h = \strtolower(\trim((string) $header));
                $idx = (int) $index;

                if (\str_contains($h, 'zweck') || \str_contains($h, 'remittance') || \str_contains($h, 'betreff')) {
                    $guessedId = $idx;
                }
                if (\str_contains($h, 'betrag') || \str_contains($h, 'amount') || \str_contains($h, 'umsatz')) {
                    $guessedAmount = $idx;
                }
                if (\str_contains($h, 'buchungstag') || \str_contains($h, 'valuta') || \str_contains($h, 'date')) {
                    $guessedDate = $idx;
                }
                if (
                    \str_contains($h, 'zahlungspflicht')
                    || \str_contains($h, 'auftraggeber')
                    || \str_contains($h, 'beguenstigt')
                    || \str_contains($h, 'begünstigt')
                    || \str_contains($h, 'kontoinhaber')
                    || \str_contains($h, 'name')
                ) {
                    $guessedSender = $idx;
                }
                if (!\str_contains($h, 'waehrung') && !\str_contains($h, 'währung') && !\str_contains($h, 'currency')) {
                    continue;
                }

                $guessedCurrency = $idx;
            }

            return new BankImportAnalysisDto(
                headers: $headers,
                previewRow: $previewRow,
                guessedId: $guessedId,
                guessedAmount: $guessedAmount,
                guessedDate: $guessedDate,
                guessedSender: $guessedSender,
                guessedCurrency: $guessedCurrency,
            );
        } catch (Exception $e) {
            \error_log('AnalyzeBankImportHandler Error: ' . $e->getMessage());

            return new BankImportAnalysisDto([], []);
        }
    }
}
