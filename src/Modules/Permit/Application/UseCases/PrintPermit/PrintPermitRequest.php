<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Application\Exception\ValidationException;

final readonly class PrintPermitRequest
{
    public function __construct(
        public string $code,
    ) {
    }

    public static function fromArray(array $get): self
    {
        $code = \strtoupper(\trim((string) ($get['code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessage('Kein Code übergeben.');
        }

        return new self($code);
    }
}
