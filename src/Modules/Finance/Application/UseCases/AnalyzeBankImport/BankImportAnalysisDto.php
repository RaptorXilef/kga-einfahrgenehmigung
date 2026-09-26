<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

/**
 * Ergebnis-DTO der CSV-Vorabanalyse inklusive automatisch erkannter Spaltenindizes.
 * Standard-Indizes (0-basiert):
 * - Col 2 (Index 1): Überweisungsdatum / Buchungstag
 * - Col 5 (Index 4): Verwendungszweck / Betreff
 * - Col 12 (Index 11): Name des Überweisenden
 * - Col 15 (Index 14): Betrag / Euro
 * - Col 16 (Index 15): Währung (EUR)
 */
final readonly class BankImportAnalysisDto
{
    public function __construct(
        public array $headers,
        public array $previewRow,
        public int $guessedId = 4,
        public int $guessedAmount = 14,
        public int $guessedDate = 1,
        public int $guessedSender = 11,
        public int $guessedCurrency = 15,
    ) {
    }
}
