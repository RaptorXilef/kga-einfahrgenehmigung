<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

final readonly class BankImportProcessRequest
{
    private function __construct(
        public string $tempFile,
        public int $idColumn,
        public int $amountColumn,
        public int $dateColumn,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $file = \trim((string) ($post['temp_file'] ?? ''));
        if ($file === '' || ! \file_exists($file)) {
            throw ValidationException::withMessage('Temporäre Importdatei nicht gefunden.');
        }

        return new self(
            tempFile: $file,
            idColumn: (int) ($post['col_id'] ?? 4),
            amountColumn: (int) ($post['col_amount'] ?? 14),
            dateColumn: (int) ($post['col_date'] ?? 1),
        );
    }
}
