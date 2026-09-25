<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\PermitIntegrationInterface;
use App\Modules\Permit\Application\UseCases\GetPermitHistory\GetPermitHistoryHandler;
use App\Modules\Permit\Application\UseCases\GetPermitHistory\GetPermitHistoryQuery;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use Generator;
use Override;
use PDO;

/**
 * Adapter-Implementierung für externe Bounded Contexts.
 * Kapselt alle lesenden und schreibenden Cross-Module-Zugriffe auf den Permit-Datenbestand.
 */
final readonly class PermitIntegrationService implements PermitIntegrationInterface
{
    public function __construct(
        private GetPermitHistoryHandler $historyHandler,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private PDO $pdo,
        private ConfigInterface $config,
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
        $stmt = $this->pdo->query('SELECT code, name, kennzeichen, status, preis FROM permits');
        $allCodes = [];
        $unpaidCodes = [];
        $unpaidPlates = [];
        $prices = [];

        if ($stmt !== false) {
            while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $c = (string) $row['code'];
                $allCodes[$c] = true;
                $prices[$c] = (float) $row['preis'];

                if ($row['status'] === 'bezahlt') {
                    continue;
                }

                $unpaidCodes[$c] = (string) $row['name'];
                $unpaidPlates[$c] = (string) $row['kennzeichen'];
            }
        }

        return [
            'allCodes' => $allCodes,
            'unpaidCodes' => $unpaidCodes,
            'unpaidPlates' => $unpaidPlates,
            'prices' => $prices,
        ];
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
        $validTplKeys = [];
        $permitTemplates = $this->config->getArray('permit_templates');

        if ($type !== 'all') {
            foreach ($permitTemplates as $k => $tpl) {
                if (!(($tpl['type'] ?? 'standard') === $type)) {
                    continue;
                }

                $validTplKeys[] = $k;
            }
            if ($validTplKeys === []) {
                return;
            }
        }

        $whereParts = ['DATE(erstellt) >= ? AND DATE(erstellt) <= ?'];
        $binds = [$start, $end];

        if ($validTplKeys !== []) {
            $in = \str_repeat('?,', \count($validTplKeys) - 1) . '?';
            $whereParts[] = "template_key IN ($in)";
            $binds = \array_merge($binds, $validTplKeys);
        }

        if ($searchQuery !== '') {
            $whereParts[] = "CONCAT_WS(' ', code, name, IFNULL(email, ''), kennzeichen, LPAD(parzelle, 4, '0'), zweck) LIKE ?";
            $binds[] = '%' . \strtolower(\trim($searchQuery)) . '%';
        }

        $whereStr = \implode(' AND ', $whereParts);
        $cols = 'code, template_key, name, parzelle, kennzeichen, zweck, preis, status, erstellt, bezahlt_am';

        $sql = "
            SELECT {$cols} FROM permits WHERE {$whereStr}
            UNION ALL
            SELECT {$cols} FROM permits_archive WHERE {$whereStr}
            ORDER BY erstellt ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(\array_merge($binds, $binds));

        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            yield $row;
        }
    }
}
