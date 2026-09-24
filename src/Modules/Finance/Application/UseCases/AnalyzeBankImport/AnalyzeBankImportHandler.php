<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Exception;
use League\Csv\Reader;
use Override;

/**
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
    public function handle(mixed $query): BankImportAnalysisDto
    {
        $csv = $this->infrastructure->normalizeAndOpenCsv($query->tempFilePath);

        if (!$csv instanceof Reader) {
            return new BankImportAnalysisDto([], []);
        }

        try {
            $iterator = $csv->getIterator();
            $iterator->rewind();

            $headers = $iterator->valid() ? $iterator->current() : [];
            $iterator->next();
            $previewRow = $iterator->valid() ? $iterator->current() : [];

            return new BankImportAnalysisDto(
                \is_array($headers) ? $headers : [],
                \is_array($previewRow) ? $previewRow : [],
            );
        } catch (Exception $e) {
            \error_log('AnalyzeBankImportHandler Error: ' . $e->getMessage());

            return new BankImportAnalysisDto([], []);
        }
    }
}
