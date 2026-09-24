<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Services;

use App\Contracts\Integration\PermitIntegrationInterface;
use App\Modules\Permit\Application\UseCases\GetPermitHistory\GetPermitHistoryHandler;
use App\Modules\Permit\Application\UseCases\GetPermitHistory\GetPermitHistoryQuery;
use Override;

/**
 * Adapter-Implementierung für externe Bounded Contexts.
 */
final readonly class PermitIntegrationService implements PermitIntegrationInterface
{
    public function __construct(
        private GetPermitHistoryHandler $historyHandler,
    ) {
    }

    #[Override]
    public function hasPermits(string $email): bool
    {
        $permits = $this->historyHandler->handle(new GetPermitHistoryQuery($email));

        return \count($permits) > 0;
    }
}
