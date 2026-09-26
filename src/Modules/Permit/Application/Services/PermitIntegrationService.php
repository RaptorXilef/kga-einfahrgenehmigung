<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Services;

use App\Contracts\Integration\PermitIntegrationInterface;
use App\Modules\Permit\Application\UseCases\GetBankImportPermitData\GetBankImportPermitDataHandler;
use App\Modules\Permit\Application\UseCases\GetBankImportPermitData\GetBankImportPermitDataQuery;
use App\Modules\Permit\Application\UseCases\GetPermitHistory\GetPermitHistoryHandler;
use App\Modules\Permit\Application\UseCases\GetPermitHistory\GetPermitHistoryQuery;
use App\Modules\Permit\Application\UseCases\StreamPermitsForFinanceExport\StreamPermitsForFinanceExportHandler;
use App\Modules\Permit\Application\UseCases\StreamPermitsForFinanceExport\StreamPermitsForFinanceExportQuery;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use Generator;
use Override;

/**
 * Adapter-Implementierung für externe Bounded Contexts.
 * Delegiert alle Cross-Module-Anfragen strikt an dedizierte Use-Case-Handler und Repositories (100% frei von direktem PDO).
 */
final readonly class PermitIntegrationService implements PermitIntegrationInterface
{
    public function __construct(
        private GetPermitHistoryHandler $historyHandler,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private GetBankImportPermitDataHandler $bankImportDataHandler,
        private StreamPermitsForFinanceExportHandler $financeExportStreamHandler,
    ) {
    }

    #[Override]
    public function hasPermits(string $email): bool
    {
        $permits = $this->historyHandler->handle(new GetPermitHistoryQuery($email));

        return \count($permits) > 0;
    }

    #[Override]
    public function anonymizeArchive(int $yearsThreshold = 10): int
    {
        return $this->archiveRepository->anonymizeOldRecords($yearsThreshold);
    }

    #[Override]
    public function getPermitDataForBankImport(): array
    {
        return $this->bankImportDataHandler->handle(new GetBankImportPermitDataQuery());
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    #[Override]
    public function yieldPermitsForFinanceExport(
        string $start,
        string $end,
        string $type,
        string $searchQuery,
    ): Generator {
        return $this->financeExportStreamHandler->handle(
            new StreamPermitsForFinanceExportQuery(
                start: $start,
                end: $end,
                type: $type,
                searchQuery: $searchQuery,
            ),
        );
    }
}
