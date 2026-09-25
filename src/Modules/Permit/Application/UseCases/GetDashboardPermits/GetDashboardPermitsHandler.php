<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardPermits;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * @implements QueryHandlerInterface<GetDashboardPermitsQuery, DashboardPermitsResultDto>
 */
final readonly class GetDashboardPermitsHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetDashboardPermitsQuery $query
     */
    #[Override]
    public function handle(mixed $query): DashboardPermitsResultDto
    {
        // 1. Template-Filter auflösen
        $validTplKeys = [];
        if ($query->filterType !== 'all') {
            foreach ($this->config->getArray('permit_templates') as $k => $tpl) {
                if (!(($tpl['type'] ?? 'standard') === $query->filterType)) {
                    continue;
                }

                $validTplKeys[] = $k;
            }
            if ($validTplKeys === []) {
                return new DashboardPermitsResultDto([], [], [], [], 0, 0, 0, 0, 0);
            }
        }

        // 2. WHERE Bedingung und Binds generieren (Positional Parameters für Sicherheit & Performance)
        $whereParts = ['DATE(erstellt) >= ? AND DATE(erstellt) <= ?'];
        $binds = [$query->filterStart, $query->filterEnd];

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

        // Spezifisches Archiv-Where
        $archiveWhereStr = $whereStr . ' AND (YEAR(erstellt) >= ? OR YEAR(von) >= ?)';
        $archiveBinds = \array_merge($binds, [$query->minArchiveYear, $query->minArchiveYear]);

        // 3. Counts aggregieren (Blitzschnell via SUM CASE)
        $sqlCountPermits = "SELECT
            SUM(CASE WHEN bis >= CURDATE() AND von <= CURDATE() AND status != 'storniert' THEN 1 ELSE 0 END) as c_active,
            SUM(CASE WHEN von > CURDATE() AND status != 'storniert' THEN 1 ELSE 0 END) as c_future,
            SUM(CASE WHEN bis < CURDATE() AND status != 'storniert' THEN 1 ELSE 0 END) as c_expired
            FROM permits WHERE {$whereStr}";

        $stmt = $this->pdo->prepare($sqlCountPermits);
        $stmt->execute($binds);
        $counts1 = $stmt->fetch(PDO::FETCH_ASSOC);

        $sqlCountArchive = "SELECT
            SUM(CASE WHEN bis >= CURDATE() AND von <= CURDATE() AND status != 'storniert' THEN 1 ELSE 0 END) as c_active,
            SUM(CASE WHEN von > CURDATE() AND status != 'storniert' THEN 1 ELSE 0 END) as c_future,
            SUM(CASE WHEN bis < CURDATE() AND status != 'storniert' THEN 1 ELSE 0 END) as c_expired
            FROM permits_archive WHERE {$archiveWhereStr}";

        $stmt = $this->pdo->prepare($sqlCountArchive);
        $stmt->execute($archiveBinds);
        $counts2 = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM permits_cancelled WHERE {$whereStr}");
        $stmt->execute($binds);
        $countCancelled = (int) $stmt->fetchColumn();

        $c1 = \is_array($counts1) ? $counts1 : [];
        $c2 = \is_array($counts2) ? $counts2 : [];

        $countActive = (int) ($c1['c_active'] ?? 0) + (int) ($c2['c_active'] ?? 0);
        $countFuture = (int) ($c1['c_future'] ?? 0) + (int) ($c2['c_future'] ?? 0);
        $countExpired = (int) ($c1['c_expired'] ?? 0) + (int) ($c2['c_expired'] ?? 0);

        // 4. Nur Items (25 Stück) für die 4 Tabs fetchen
        $activeItems = $this->fetchItems('tab-active', $query, $whereStr, $archiveWhereStr, $binds, $archiveBinds);
        $futureItems = $this->fetchItems('tab-future', $query, $whereStr, $archiveWhereStr, $binds, $archiveBinds);
        $expiredItems = $this->fetchItems('tab-expired', $query, $whereStr, $archiveWhereStr, $binds, $archiveBinds);
        $cancelledItems = $this->fetchItems('tab-cancelled', $query, $whereStr, $archiveWhereStr, $binds, $archiveBinds);

        return new DashboardPermitsResultDto(
            activePermitsDto: $this->mapRowsToDto($activeItems, 'tab-active'),
            futurePermitsDto: $this->mapRowsToDto($futureItems, 'tab-future'),
            expiredPermitsDto: $this->mapRowsToDto($expiredItems, 'tab-expired'),
            cancelledPermitsDto: $this->mapRowsToDto($cancelledItems, 'tab-cancelled'),
            countActive: $countActive,
            countFuture: $countFuture,
            countExpired: $countExpired,
            countCancelled: $countCancelled,
            countUnpaid: 0, // Unpaid wird in der Action vom FinanceHandler gesetzt
        );
    }

    private function fetchItems(string $tab, GetDashboardPermitsQuery $query, string $whereStr, string $archiveWhereStr, array $binds, array $archiveBinds): array
    {
        $page = $query->activeTab === $tab ? $query->page : 1;
        $offset = ($page - 1) * $query->limit;
        $limit = $query->limit;

        $cols = 'code, template_key, name, email, kennzeichen, parzelle, typ, firma, zweck, preis, von, bis, status, is_suspended, suspension_reason, erstellt';

        if ($tab === 'tab-cancelled') {
            $sql = "SELECT {$cols} FROM permits_cancelled WHERE {$whereStr} ORDER BY erstellt DESC LIMIT {$limit} OFFSET {$offset}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($binds);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $tabCond = '';
        if ($tab === 'tab-active') {
            $tabCond = " AND bis >= CURDATE() AND von <= CURDATE() AND status != 'storniert' ";
        } elseif ($tab === 'tab-future') {
            $tabCond = " AND von > CURDATE() AND status != 'storniert' ";
        } elseif ($tab === 'tab-expired') {
            $tabCond = " AND bis < CURDATE() AND status != 'storniert' ";
        }

        $sql = "
            SELECT * FROM (
                SELECT {$cols} FROM permits WHERE {$whereStr} {$tabCond}
                UNION ALL
                SELECT {$cols} FROM permits_archive WHERE {$archiveWhereStr} {$tabCond}
            ) as combined
            ORDER BY erstellt DESC LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(\array_merge($binds, $archiveBinds));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function mapRowsToDto(array $rows, string $activeTab): array
    {
        $dtos = [];
        $now = $this->clock->now();
        $nowDateStr = $now->format('Y-m-d');
        $requirePayment = $this->config->getBool('require_payment_for_validity', false);
        $vehicleConfig = $this->config->getArray('vehicle_types');

        foreach ($rows as $row) {
            $isSuspended = (bool) $row['is_suspended'];
            $erstellt = new DateTimeImmutable((string) $row['erstellt']);
            $von = new DateTimeImmutable((string) $row['von']);
            $bis = new DateTimeImmutable((string) $row['bis']);

            $isExpired = $bis->format('Y-m-d') < $nowDateStr;
            $isFuture = $von->format('Y-m-d') > $nowDateStr;
            $isPaid = $row['status'] === 'bezahlt';

            $isValid = !$isSuspended && !$isFuture && !$isExpired && (!$requirePayment || $isPaid);

            $rowClass = $isSuspended ? 'c-table__row--danger' : '';
            if ($isExpired) {
                $rowClass = 'u-opacity-75';
            }

            $vKey = (string) $row['typ'];
            $vCfg = $vehicleConfig[$vKey] ?? null;
            $vehicleIcon = (string) ($vCfg['icon'] ?? 'assets/img/icons/warning.webp');
            $vehicleLabel = (string) ($vCfg['label'] ?? 'Ehem. ' . \strtoupper($vKey));

            $emailHtml = '<small class="u-color-muted"><em>keine Angabe</em></small>';
            $rawEmail = \trim((string) ($row['email'] ?? ''));
            if ($rawEmail !== '' && $rawEmail !== '0') {
                $safeMail = \htmlspecialchars($rawEmail, \ENT_QUOTES, 'UTF-8');
                $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
                $emailHtml = <<<HTML
                    <div class="u-flex u-align-center u-gap-xs">
                        <img src="assets/img/icons/envelope.webp" class="c-icon c-icon--inline" loading="lazy" alt="">
                        <small><a href="mailto:{$safeMail}" class="u-text-link c-table__mail-link">{$wbrMail}</a></small>
                    </div>
                    HTML;
            }

            $countdownText = '';
            $countdownBadgeClass = 'c-badge--primary';

            if ($isFuture) {
                $daysUntil = (int) $now->diff($von)->format('%r%a');
                $countdownText = $daysUntil === 1 ? 'Morgen' : "In {$daysUntil} Tagen";
            } elseif (!$isExpired) {
                $remaining = (int) $now->diff($bis)->format('%r%a');
                $countdownText = $remaining === 0 ? 'Läuft heute ab' : "Noch {$remaining} Tage";
                $countdownBadgeClass = $remaining <= 1 ? 'c-badge--danger' : 'c-badge--primary';
            }

            $statusBadgeHtml = '';
            if ($isSuspended) {
                $reason = \htmlspecialchars((string) ($row['suspension_reason'] ?? ''));
                $statusBadgeHtml = "<span class=\"c-badge c-badge--danger\" title=\"{$reason}\">GESPERRT</span>";
            } elseif (!$isValid && !$isFuture && !$isExpired) {
                $statusBadgeHtml = '<span class="c-badge c-badge--warning" title="Zeitraum gültig, aber Zahlung ausstehend">UNBEZAHLT</span>';
            } elseif ($isValid) {
                $statusBadgeHtml = '<span class="c-badge c-badge--success">AKTIV</span>';
            }

            if ($activeTab === 'tab-cancelled' || $row['status'] === 'storniert') {
                $statusBadgeHtml = '<span class="c-badge c-badge--danger">STORNIERT</span>';
            }

            $statusSortValue = $isSuspended ? '2' : ($isFuture ? '1' : '0');

            $dtos[] = new DashboardPermitDto(
                code: (string) $row['code'],
                ownerName: (string) $row['name'],
                plotNumber: \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                licensePlate: (string) ($row['kennzeichen'] ?: '---'),
                vehicleIcon: $vehicleIcon,
                vehicleLabel: $vehicleLabel,
                emailHtml: $emailHtml,
                validFromDate: $von->format('d.m.Y'),
                validUntilDate: $bis->format('d.m.Y'),
                createdAtDate: $erstellt->format('d.m.Y'),
                createdAtTime: $erstellt->format('H:i'),
                priceFormatted: \number_format((float) $row['preis'], 2, ',', '.') . ' €',
                rowClass: $rowClass,
                countdownText: $countdownText,
                countdownBadgeClass: $countdownBadgeClass,
                statusBadgeHtml: $statusBadgeHtml,
                statusSortValue: $statusSortValue,
                isSuspended: $isSuspended,
                suspendIcon: $isSuspended ? 'unlock.webp' : 'denied.webp',
                suspendTitle: $isSuspended ? 'Wieder freigeben' : 'Sperren',
                suspendActionUrl: $isSuspended ? 'unsuspend_permit' : 'suspend_permit',
                suspensionReason: isset($row['suspension_reason']) ? (string) $row['suspension_reason'] : null,
            );
        }

        return $dtos;
    }
}
