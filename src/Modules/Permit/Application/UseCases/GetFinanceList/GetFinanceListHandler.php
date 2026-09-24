<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetFinanceList;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * @implements QueryHandlerInterface<GetFinanceListQuery, array<FinancePermitDto>>
 */
final readonly class GetFinanceListHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param GetFinanceListQuery $query
     *
     * @return array<FinancePermitDto>
     */
    #[Override]
    public function handle(mixed $query): array
    {
        $stmt = $this->pdo->query("SELECT * FROM permits WHERE status = 'offen'");

        $now = new DateTimeImmutable('today');
        $dueDaysCfg = $this->config->getInt('payment_due_days', 14);
        $daysBeforeValidity = $this->config->getInt('payment_due_days_before_validity', 2);
        $notifyDays = $this->config->getInt('payment_due_days_notify', 2);
        $cooldownDays = $this->config->getInt('payment_reminder_cooldown_days', 7);
        $vConfig = $this->config->getArray('vehicle_types');

        $dtos = [];
        $sortDeadlines = [];

        // Memory Safe Unbuffered Loop
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $createdAt = new DateTimeImmutable($row['erstellt']);
            $validFrom = new DateTimeImmutable($row['von']);
            $isSuspended = (bool) $row['is_suspended'];

            // 1. Deadline berechnen (Logik aus PermitService gespiegelt, aber hochperformant auf rohen Daten)
            $fallbackDueDate = $createdAt->modify("+{$dueDaysCfg} days")->setTime(23, 59, 59);
            $dynamicDueDate = $validFrom->modify("-{$daysBeforeValidity} days")->setTime(23, 59, 59);
            $deadline = $dynamicDueDate > $fallbackDueDate ? $dynamicDueDate : $fallbackDueDate;

            $sortDeadlines[] = $deadline->getTimestamp();

            // 2. Overdue Level berechnen
            $overdueLevel = 0;
            $staffAlertThreshold = $deadline->modify("+{$notifyDays} days");
            if ($now > $staffAlertThreshold) {
                $overdueLevel = 2;
            } elseif ($now > $deadline) {
                $overdueLevel = 1;
            }

            // 3. Erinnerungs-Logik (Cooldown)
            $lastRemDate = null;
            $isOnCooldown = false;
            $reminderClass = null;
            $reminderText = null;

            if (!empty($row['last_reminder_at'])) {
                $lastRemDate = new DateTimeImmutable($row['last_reminder_at']);
                $cooldownDate = $lastRemDate->modify("+{$cooldownDays} days");
                $isOnCooldown = $now < $cooldownDate;

                $reminderClass = $isOnCooldown ? 'u-color-primary' : 'u-color-danger';
                $reminderText = 'Erinnert: ' . $lastRemDate->format('d.m.Y');
            }

            // 4. Anzeige-Formatierungen & CSS UI Klassen direkt aus dem DTO
            $diff = $now->diff($deadline);
            $daysDiff = (int) $diff->format('%r%a');

            $rowClass = $overdueLevel === 2 || $isSuspended ? 'c-table__row--danger' : '';

            $vKey = $row['typ'];
            $vehicleIcon = $vConfig[$vKey]['icon'] ?? 'assets/img/icons/warning.webp';
            $vehicleLabel = $vConfig[$vKey]['label'] ?? 'Ehem. ' . \strtoupper($vKey);

            $deadlineBadgeClass = 'c-badge--outline';
            $deadlineIcon = null;
            $deadlineText = "Noch {$daysDiff} Tage";

            if ($overdueLevel === 2) {
                $deadlineBadgeClass = 'c-badge--danger';
                $deadlineIcon = 'siren.webp';
                $deadlineText = \str_replace('-', '', (string) $daysDiff) . ' TAGE ÜBERFÄLLIG';
            } elseif ($overdueLevel === 1) {
                $deadlineBadgeClass = 'c-badge--warning';
                $deadlineText = 'MAHNFRIST ABGELAUFEN';
            }

            $emailHtml = '<small class="u-color-muted"><em>Keine Mail</em></small>';
            if (!empty($row['email'])) {
                $safeMail = \htmlspecialchars($row['email'], \ENT_QUOTES, 'UTF-8');
                $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
                $emailHtml = <<<HTML
                        <small><a href="mailto:{$safeMail}" class="u-text-link c-table__mail-link">{$wbrMail}</a></small>
                    HTML;
            }

            // Logik für die PHTML-Buttons in die Domain holen
            $reminderButtonClass = $isOnCooldown ? 'c-button--secondary' : 'c-button--danger';
            $reminderButtonTitle = 'Zahlungserinnerung senden' . ($isOnCooldown ? ' (Cooldown aktiv)' : '');
            $sortSuspendedValue = $isSuspended ? '1' : '0';

            $dtos[] = new FinancePermitDto(
                code: (string) $row['code'],
                ownerName: (string) $row['name'],
                plotNumber: \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                licensePlate: (string) $row['kennzeichen'],
                vehicleIcon: $vehicleIcon,
                vehicleLabel: $vehicleLabel,
                emailHtml: $emailHtml,
                priceRaw: (float) $row['preis'],
                priceFormatted: \number_format((float) $row['preis'], 2, ',', '.') . ' €',
                rowClass: $rowClass,
                deadlineDate: $deadline->format('d.m.Y'),
                deadlineBadgeClass: $deadlineBadgeClass,
                deadlineIcon: $deadlineIcon,
                deadlineText: $deadlineText,
                reminderClass: $reminderClass,
                reminderText: $reminderText,
                reminderButtonClass: $reminderButtonClass,
                reminderButtonTitle: $reminderButtonTitle,
                sortSuspendedValue: $sortSuspendedValue,
                isOnCooldown: $isOnCooldown,
                isSuspended: $isSuspended,
                suspensionReason: $row['suspension_reason'] ?? null,
            );
        }

        \array_multisort($sortDeadlines, \SORT_ASC, \SORT_NUMERIC, $dtos);

        return $dtos;
    }
}
