<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Application\Exception\ValidationException;

/**
 * Validiert die Eingabeparameter für den CSV-Bankabgleich komplett ohne Dateisystem-I/O.
 */
final readonly class BankImportProcessRequest
{
    private function __construct(
        public string $tempFile,
        public int $idColumn,
        public int $amountColumn,
        public int $dateColumn,
        public int $senderColumn,
        public int $currencyColumn,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $file = \trim((string) ($post['temp_file'] ?? ''));
        if ($file === '' || !\str_ends_with(\strtolower($file), '.csv')) {
            throw ValidationException::withMessage('Temporäre Importdatei nicht angegeben oder ungültig.');
        }

        return new self(
            tempFile: $file,
            idColumn: (int) ($post['col_id'] ?? 4),
            amountColumn: (int) ($post['col_amount'] ?? 14),
            dateColumn: (int) ($post['col_date'] ?? 1),
            senderColumn: (int) ($post['col_sender'] ?? 11),
            currencyColumn: (int) ($post['col_currency'] ?? 15),
        );
    }
}
