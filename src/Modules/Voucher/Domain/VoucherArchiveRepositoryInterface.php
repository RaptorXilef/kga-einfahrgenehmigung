<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Domain;

interface VoucherArchiveRepositoryInterface
{
    public function archiveRedemption(string $code, string $userName, string $userPlot): void;
}
