<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SearchPermits;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use PDO;

/**
 * @implements QueryHandlerInterface<SearchPermitsQuery, array>
 */
final readonly class SearchPermitsHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function handle(mixed $query): array
    {
        $validTplKeys = [];
        if ($query->templateType !== 'all') {
            foreach ($this->config->get('permit_templates', []) as $k => $tpl) {
                if (($tpl['type'] ?? 'standard') === $query->templateType) {
                    $validTplKeys[] = $k;
                }
            }
            if (empty($validTplKeys)) {
                return ['items' => [], 'total' => 0];
            }
        }

        $binds = [];
        $whereParts = [];

        if (!empty($validTplKeys)) {
            $in = \str_repeat('?,', \count($validTplKeys) - 1) . '?';
            $whereParts[] = "template_key IN ($in)";
            $binds = \array_merge($binds, $validTplKeys);
        }

        if ($query->query !== '') {
            $whereParts[] = "CONCAT_WS(' ', code, name, IFNULL(email, ''), kennzeichen, LPAD(parzelle, 4, '0'), zweck) LIKE ?";
            $binds[] = '%' . \strtolower(\trim($query->query)) . '%';
        }

        $whereStr = empty($whereParts) ? '1=1' : \implode(' AND ', $whereParts);

        $sqlParts = [];
        $allBinds = [];
        $baseCols = 'code, template_key, name, email, kennzeichen, parzelle, preis, status, von, bis, zweck, erstellt';

        // Aktive und abgelaufene (aus der Haupt-Tabelle)
        if (\in_array($query->tab, ['all', 'active', 'expired'], true)) {
            $statusCond = '';
            if ($query->tab === 'active') {
                $statusCond = ' AND bis >= CURDATE()';
            } elseif ($query->tab === 'expired') {
                $statusCond = ' AND bis < CURDATE()';
            }
            $sqlParts[] = "SELECT $baseCols, 0 AS is_archived FROM permits WHERE $whereStr $statusCond";
            $allBinds = \array_merge($allBinds, $binds);
        }

        // Archivierte (aus der Archiv-Tabelle)
        if (\in_array($query->tab, ['all', 'archive'], true)) {
            $sqlParts[] = "SELECT $baseCols, 1 AS is_archived FROM permits_archive WHERE $whereStr";
            $allBinds = \array_merge($allBinds, $binds);
        }

        if (empty($sqlParts)) {
            return ['items' => [], 'total' => 0];
        }

        $fullSql = \implode(' UNION ALL ', $sqlParts) . ' ORDER BY erstellt DESC';

        $stmt = $this->pdo->prepare($fullSql);
        $stmt->execute($allBinds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = \count($rows);
        $offset = ($query->page - 1) * $query->limit;
        $items = \array_slice($rows, $offset, $query->limit);

        // Daten flach mappen, wie es die API / das Vue.js Frontend erwartet
        $formattedItems = \array_map(function (array $row) {
            return [
                'bis' => \date('d.m.Y', \strtotime($row['bis'])),
                'code' => $row['code'],
                'email' => $row['email'] ?: '',
                'erstellt' => \date('d.m.Y H:i', \strtotime($row['erstellt'])),
                'is_archived' => (bool) $row['is_archived'],
                'kennzeichen' => $row['kennzeichen'],
                'name' => $row['name'],
                'parzelle' => \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                'preis' => (float) $row['preis'],
                'status' => $row['status'],
                'template_key' => $row['template_key'],
                'von' => \date('d.m.Y', \strtotime($row['von'])),
                'zweck' => $row['zweck'],
            ];
        }, $items);

        return ['items' => $formattedItems, 'total' => $total];
    }
}
