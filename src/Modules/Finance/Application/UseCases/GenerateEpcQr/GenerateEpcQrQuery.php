<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\GenerateEpcQr;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GenerateEpcQrQuery implements QueryInterface
{
    public function __construct(
        public float $amount,
        public string $reference,
    ) {
    }
}
