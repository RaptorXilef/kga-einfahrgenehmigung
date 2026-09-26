<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure;

use App\Contracts\Integration\PermitIntegrationInterface;
use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use App\Modules\Finance\Application\DTO\UnpaidPermitsDto;
use Override;

/**
 * Bezieht die für den Bankabgleich relevanten Permit-Daten modulsicher über das PermitIntegrationInterface.
 */
final readonly class PdoUnpaidPermitProvider implements UnpaidPermitProviderInterface
{
    public function __construct(private PermitIntegrationInterface $permitIntegration)
    {
    }

    #[Override]
    public function getPermitDataForImport(): UnpaidPermitsDto
    {
        $data = $this->permitIntegration->getPermitDataForBankImport();

        return new UnpaidPermitsDto(
            allCodes: $data['allCodes'],
            unpaidCodes: $data['unpaidCodes'],
            unpaidPlates: $data['unpaidPlates'],
            prices: $data['prices'],
            records: $data['records'],
        );
    }
}
