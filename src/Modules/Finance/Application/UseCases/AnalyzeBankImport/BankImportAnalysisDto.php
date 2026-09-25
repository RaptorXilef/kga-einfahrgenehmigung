<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

/**
 * Ergebnis-DTO der CSV-Vorabanalyse inklusive automatisch erkannter Spaltenindizes.
 */
final readonly class BankImportAnalysisDto
{
    public function __construct(
        public array $headers,
        public array $previewRow,
        public int $guessedId = 4,
        public int $guessedAmount = 14,
        public int $guessedDate = 1,
    ) {
    }
}
