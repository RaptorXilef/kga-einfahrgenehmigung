<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Modules\Permit\Domain\PermitStatus;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;

final readonly class CreateManualPermitCommand implements CommandInterface
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
        public Price $manualPrice,
        public PermitStatus $status,
        public ?string $internerKommentar,
        public array $agreements,
        public bool $sendEmail,
    ) {
    }
}
