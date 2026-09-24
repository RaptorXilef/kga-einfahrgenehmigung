<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherArchive;

/**
 * Stark typisiertes Read-Model DTO für einen Eintrag im Gutschein-Archiv.
 */
final readonly class VoucherArchiveItemDto
{
    public function __construct(
        public string $code,
        public string $redeemedAtFormatted,
        public string $userName,
        public string $userPlot,
    ) {
    }
}
