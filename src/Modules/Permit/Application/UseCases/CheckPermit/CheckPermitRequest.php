<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CheckPermit;

use App\Application\Exception\ValidationException;

final readonly class CheckPermitRequest
{
    public function __construct(
        public string $code,
        public string $token,
    ) {
    }

    public static function fromArray(array $get): self
    {
        $code = \strtoupper(\trim((string) ($get['code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessage('Kein Code übergeben.');
        }

        return new self($code, (string) ($get['token'] ?? ''));
    }
}
