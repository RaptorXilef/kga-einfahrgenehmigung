<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use App\SharedKernel\Domain\ValueObject\VoucherCode;

final readonly class SubmitPermitRequestCommand implements CommandInterface
{
    public function __construct(
        public string $name,
        public ?EmailAddress $email,
        public PlotNumber $parzelle,
        public string $typ,
        public LicensePlate $kennzeichen,
        public ?string $firma,
        public string $zweck,
        public TemplateKey $templateKey,
        public string $datumVon,
        public string $datumBis,
        public array $agreements,
        public ?VoucherCode $voucher,
        public ?string $editToken = null,
        public ?string $sessionEmail = null,
    ) {
    }
}
