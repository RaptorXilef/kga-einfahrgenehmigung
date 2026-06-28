<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

final readonly class HistoryCancelPermitRequest
{
    private function __construct(
        public string $code,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $code = \trim((string) ($post['code'] ?? ''));
        if ($code === '') {
            throw ValidationException::withMessage('Fehlender Genehmigungscode.');
        }

        return new self($code);
    }
}
