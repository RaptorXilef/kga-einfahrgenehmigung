<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Query zur Erzeugung des binären A4-PDF-Dokuments aus einem aufgelösten PermitReadDto.
 */
final readonly class GeneratePermitPdfQuery implements QueryInterface
{
    public function __construct(
        public PermitReadDto $permit,
    ) {
    }
}
