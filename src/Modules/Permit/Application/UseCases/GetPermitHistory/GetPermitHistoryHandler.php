<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Security\Sanitizer;
use DateTimeImmutable;
use PDO;

/**
 * Holt die Pächter-Historie via PDO und mappt sie in flache DTOs (Pragmatic CQRS).
 * Entities werden beim Lesen komplett umgangen.
 *
 * @implements QueryHandlerInterface<GetPermitHistoryQuery, HistoryPermitViewDto[]>
 */
final readonly class GetPermitHistoryHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    public function handle(mixed $query): array
    {
        $normalizedSearch = Sanitizer::normalizeEmail($query->email);
        $parts = \explode('@', $normalizedSearch);
        $domain = \count($parts) === 2 ? '\%' . $parts[1] : '%';

        $binds = ['domain1' => $domain, 'domain2' => $domain];
        $archiveCond = '';

        // Wenn das Vorjahres-Archiv geladen werden soll
        if ($query->archiveYear > 0) {
            $archiveCond = ' AND (YEAR(erstellt) >= :archiveYear OR YEAR(von) >= :archiveYear2)';
            $binds['archiveYear'] = $query->archiveYear;
            $binds['archiveYear2'] = $query->archiveYear;
        }

        $sql = "
            SELECT code, name, email, kennzeichen, parzelle, typ, status, von, bis, erstellt, is_suspended
            FROM permits WHERE email LIKE :domain1
            UNION ALL
            SELECT code, name, email, kennzeichen, parzelle, typ, status, von, bis, erstellt, is_suspended
            FROM permits_archive WHERE email LIKE :domain2 {$archiveCond}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);

        $now = $this->clock->now();
        $nowDateStr = $now->format('Y-m-d');
        $vConfig = $this->config->get('vehicle_types', []);

        $dueDaysCfg = (int) $this->config->get('payment_due_days', 14);
        $daysBeforeValidity = (int) $this->config->get('payment_due_days_before_validity', 2);
        $notifyDays = (int) $this->config->get('payment_due_days_notify', 2);
        $allowCancel = (bool) $this->config->get('allow_user_cancellation', true);

        $dtos = [];

        // VSA FIX: Nutzt `while` anstelle von fetchAll() um Speicher zu schonen
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Serverseitiger, exakter E-Mail Abgleich (Alias Ignorance)
            if (Sanitizer::normalizeEmail((string) $row['email']) !== $normalizedSearch) {
                continue;
            }

            $isSuspended = (bool) $row['is_suspended'];
            $erstellt = new DateTimeImmutable($row['erstellt']);
            $von = new DateTimeImmutable($row['von']);
            $bis = new DateTimeImmutable($row['bis']);

            $isExpired = $bis->format('Y-m-d') < $nowDateStr;
            $isFuture = $von->format('Y-m-d') > $nowDateStr;
            $isPaid = $row['status'] === 'bezahlt';

            $rowClass = $isExpired ? 'u-opacity-50' : '';
            $rowClass .= $isSuspended ? ' c-table__row--danger' : '';

            $vKey = $row['typ'];
            $vCfg = $vConfig[$vKey] ?? null;
            $vehicleIcon = $vCfg['icon'] ?? 'assets/img/icons/warning.webp';
            $vehicleLabel = $vCfg['label'] ?? 'Ehem. ' . \strtoupper($vKey);

            $countdownText = '';
            $countdownBadgeClass = 'c-badge--primary';

            if ($isExpired) {
                $countdownText = 'ABGELAUFEN';
                $countdownBadgeClass = 'c-badge--outline';
            } elseif ($isFuture) {
                $daysToStart = (int) $now->diff($von)->format('\%r\%a');
                $countdownText = "Startet in {$daysToStart} Tagen";
            } else {
                $diff = $now->diff($bis);
                $remaining = (int) $diff->format('%r%a');
                $countdownText = $remaining === 0 ? 'Läuft heute ab' : "Noch {$remaining} Tage";
                $countdownBadgeClass = $remaining <= 1 ? 'c-badge--danger' : 'c-badge--primary';
            }

            $statusText = \strtoupper((string) $row['status']);
            $statusBadgeClass = 'c-badge--danger';

            if ($isPaid) {
                $statusBadgeClass = 'c-badge--success';
            } else {
                // Inline Overdue Calculation (Decoupled from PermitFinancialCalculator Domain Service)
                $fallbackDueDate = $erstellt->modify("+{$dueDaysCfg} days")->setTime(23, 59, 59);
                $dynamicDueDate = $von->modify("-{$daysBeforeValidity} days")->setTime(23, 59, 59);
                $deadline = $dynamicDueDate > $fallbackDueDate ? $dynamicDueDate : $fallbackDueDate;
                $staffAlertThreshold = $deadline->modify("+{$notifyDays} days");

                $overdueLevel = 0;
                if ($now > $staffAlertThreshold) {
                    $overdueLevel = 2;
                } elseif ($now > $deadline) {
                    $overdueLevel = 1;
                }

                if ($overdueLevel === 2) {
                    $statusText = 'ZAHLUNG ÜBERFÄLLIG';
                } elseif ($overdueLevel === 1) {
                    $statusText = 'MAHNFRIST';
                } else {
                    $statusText = 'ZAHLUNG OFFEN';
                }
            }

            $canCancel = $allowCancel && $isFuture && !$isPaid && !$isSuspended;

            $dtos[] = new HistoryPermitViewDto(
                code: (string) $row['code'],
                ownerName: (string) $row['name'],
                plotNumber: \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                vehicleIcon: $vehicleIcon,
                vehicleLabel: $vehicleLabel,
                licensePlate: (string) $row['kennzeichen'] ?: '---',
                validFromDate: $von->format('d.m.Y'),
                validUntilDate: $bis->format('d.m.Y'),
                isExpired: $isExpired,
                rowClass: \trim($rowClass),
                countdownText: $countdownText,
                countdownBadgeClass: $countdownBadgeClass,
                statusText: $statusText,
                statusBadgeClass: $statusBadgeClass,
                canCancel: $canCancel,
                createdAtTimestamp: $erstellt->format('Y-m-d H:i:s'),
            );
        }

        // Am neuesten erstellte Anträge zuerst
        \usort($dtos, fn (HistoryPermitViewDto $a, HistoryPermitViewDto $b): int => $b->createdAtTimestamp <=> $a->createdAtTimestamp);

        return $dtos;
    }
}
