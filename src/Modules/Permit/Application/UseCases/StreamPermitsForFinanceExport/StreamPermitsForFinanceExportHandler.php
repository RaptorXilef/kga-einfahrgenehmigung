<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\StreamPermitsForFinanceExport;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use Generator;
use Override;
use PDO;

/**
 * Streamt alle buchhaltungsrelevanten Genehmigungszeilen (Aktiv + Archiv) per PHP-Generator direkt aus PDO.
 *
 * @implements QueryHandlerInterface<StreamPermitsForFinanceExportQuery, Generator<int, array<string, mixed>>>
 */
final readonly class StreamPermitsForFinanceExportHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param StreamPermitsForFinanceExportQuery $query
     *
     * @return Generator<int, array<string, mixed>>
     */
    #[Override]
    public function handle(QueryInterface $query): Generator
    {
        $validTplKeys = [];
        $permitTemplates = $this->config->getArray('permit_templates');

        if ($query->type !== 'all') {
            foreach ($permitTemplates as $k => $tpl) {
                if (!(($tpl['type'] ?? 'standard') === $query->type)) {
                    continue;
                }

                $validTplKeys[] = $k;
            }
            if ($validTplKeys === []) {
                return;
            }
        }

        $whereParts = ['DATE(erstellt) >= ? AND DATE(erstellt) <= ?'];
        $binds = [$query->start, $query->end];

        if ($validTplKeys !== []) {
            $in = \str_repeat('?,', \count($validTplKeys) - 1) . '?';
            $whereParts[] = "template_key IN ($in)";
            $binds = \array_merge($binds, $validTplKeys);
        }

        if ($query->searchQuery !== '') {
            $whereParts[] = "CONCAT_WS(' ', code, name, IFNULL(email, ''), kennzeichen, LPAD(parzelle, 4, '0'), zweck) LIKE ?";
            $binds[] = '%' . \strtolower(\trim($query->searchQuery)) . '%';
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
