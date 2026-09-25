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

            $guessedId = 4;
            $guessedAmount = 14;
            $guessedDate = 1;

            foreach ($headers as $index => $header) {
                $h = \strtolower(\trim((string) $header));
                if (\str_contains($h, 'zweck') || \str_contains($h, 'remittance')) {
                    $guessedId = (int) $index;
                }
                if (\str_contains($h, 'betrag') || \str_contains($h, 'amount')) {
                    $guessedAmount = (int) $index;
                }
                if (!\str_contains($h, 'buchungstag') && !\str_contains($h, 'valuta') && !\str_contains($h, 'date')) {
                    continue;
                }

                $guessedDate = (int) $index;
            }

            return new BankImportAnalysisDto(
                headers: $headers,
                previewRow: $previewRow,
                guessedId: $guessedId,
                guessedAmount: $guessedAmount,
                guessedDate: $guessedDate,
            );
        } catch (Exception $e) {
            \error_log('AnalyzeBankImportHandler Error: ' . $e->getMessage());

            return new BankImportAnalysisDto([], []);
        }
    }
}
