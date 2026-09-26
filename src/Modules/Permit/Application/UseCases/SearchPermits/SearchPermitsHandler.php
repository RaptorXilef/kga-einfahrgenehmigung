<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SearchPermits;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Durchsucht den aktiven und archivierten Genehmigungsbestand speicherschonend mittels SQL-Paginierung.
 *
 * @implements QueryHandlerInterface<SearchPermitsQuery, SearchPermitsResultDto>
 */
final readonly class SearchPermitsHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param SearchPermitsQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): SearchPermitsResultDto
    {
        $validTplKeys = [];
        if ($query->templateType !== 'all') {
            foreach ($this->config->getArray('permit_templates') as $k => $tpl) {
                if (!(($tpl['type'] ?? 'standard') === $query->templateType)) {
                    continue;
                }

                $validTplKeys[] = $k;
            }
            if ($validTplKeys === []) {
                return new SearchPermitsResultDto(items: [], total: 0);
            }
        }

        $binds = [];
        $whereParts = [];

        if ($validTplKeys !== []) {
            $in = \str_repeat('?,', \count($validTplKeys) - 1) . '?';
            $whereParts[] = "template_key IN ($in)";
            $binds = \array_merge($binds, $validTplKeys);
        }

        if ($query->query !== '') {
            $whereParts[] = "CONCAT_WS(' ', code, name, IFNULL(email, ''), kennzeichen, LPAD(parzelle, 4, '0'), zweck) LIKE ?";
            $binds[] = '%' . \strtolower(\trim($query->query)) . '%';
        }

        $whereStr = $whereParts === [] ? '1=1' : \implode(' AND ', $whereParts);

        $sqlParts = [];
        $allBinds = [];
        $baseCols = 'code, template_key, name, email, kennzeichen, parzelle, preis, status, von, bis, zweck, erstellt';
        $today = $this->clock->now()->format('Y-m-d');

        // Aktive und abgelaufene (aus der Haupt-Tabelle, über ClockInterface statt CURDATE())
        if (\in_array($query->tab, ['all', 'active', 'expired'], true)) {
            $statusCond = '';
            $statusBinds = [];
            if ($query->tab === 'active') {
                $statusCond = ' AND bis >= ?';
                $statusBinds[] = $today;
            } elseif ($query->tab === 'expired') {
                $statusCond = ' AND bis < ?';
                $statusBinds[] = $today;
            }
            $sqlParts[] = "SELECT $baseCols, 0 AS is_archived FROM permits WHERE $whereStr$statusCond";
            $allBinds = \array_merge($allBinds, $binds, $statusBinds);
        }

        if (\in_array($query->tab, ['all', 'archive'], true)) {
            $sqlParts[] = "SELECT $baseCols, 1 AS is_archived FROM permits_archive WHERE $whereStr";
            $allBinds = \array_merge($allBinds, $binds);
        }

        if ($sqlParts === []) {
            return new SearchPermitsResultDto(items: [], total: 0);
        }

        $unionSql = \implode(' UNION ALL ', $sqlParts);

        // 1. Speicherschonende Gesamtzählung direkt in SQL
        $countSql = "SELECT COUNT(*) FROM ({$unionSql}) AS combined_count";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($allBinds);
        $total = (int) $stmtCount->fetchColumn();

        if ($total === 0) {
            return new SearchPermitsResultDto(items: [], total: 0);
        }

        // 2. Paginiertes Laden der angefragten Seite ohne fetchAll()
        $safeLimit = \max(1, $query->limit);
        $safePage = \max(1, $query->page);
        $offset = ($safePage - 1) * $safeLimit;

        $dataSql = "SELECT * FROM ({$unionSql}) AS combined_data ORDER BY erstellt DESC LIMIT {$safeLimit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($dataSql);
        $stmt->execute($allBinds);

        $formattedItems = [];
        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $formattedItems[] = [
                'bis' => (new DateTimeImmutable((string) $row['bis']))->format('d.m.Y'),
                'code' => (string) $row['code'],
                'email' => (string) $row['email'] ?: '',
                'erstellt' => (new DateTimeImmutable((string) $row['erstellt']))->format('d.m.Y H:i'),
                'is_archived' => (bool) $row['is_archived'],
                'kennzeichen' => (string) $row['kennzeichen'],
                'name' => (string) $row['name'],
                'parzelle' => \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                'preis' => (float) $row['preis'],
                'status' => (string) $row['status'],
                'template_key' => (string) $row['template_key'],
                'von' => (new DateTimeImmutable((string) $row['von']))->format('d.m.Y'),
                'zweck' => (string) $row['zweck'],
            ];
        }

        return new SearchPermitsResultDto(items: $formattedItems, total: $total);
    }
}
