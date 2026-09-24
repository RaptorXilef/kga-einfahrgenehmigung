<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Services;

use App\Contracts\Integration\FinanceIntegrationInterface;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrHandler;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrQuery;
use Override;

/**
 * Adapter-Implementierung für externe Bounded Contexts.
 */
final readonly class FinanceIntegrationService implements FinanceIntegrationInterface
{
    public function __construct(
        private GenerateEpcQrHandler $qrHandler,
    ) {
    }

    #[Override]
    public function generateEpcQrData(float $amount, string $reference): string
    {
        return $this->qrHandler->handle(new GenerateEpcQrQuery($amount, $reference));
    }
}
