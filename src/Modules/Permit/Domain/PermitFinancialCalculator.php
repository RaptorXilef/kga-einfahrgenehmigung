<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use DateTimeImmutable;

/**
 * Domain Service für finanzielle Berechnungen rund um Genehmigungen.
 * Rein zustandslos.
 */
final readonly class PermitFinancialCalculator
{
    public function __construct(
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    public function calculatePaymentDueDate(Permit $permit): DateTimeImmutable
    {
        $dueDays = (int) $this->config->get('payment_due_days', 14);
        $daysBeforeValidity = (int) $this->config->get('payment_due_days_before_validity', 2);

        $fallbackDueDate = $permit->getCreatedAt()->modify("+{$dueDays} days")->setTime(23, 59, 59);
        $dynamicDueDate = $permit->getValidFrom()->modify("-{$daysBeforeValidity} days")->setTime(23, 59, 59);

        return $dynamicDueDate > $fallbackDueDate ? $dynamicDueDate : $fallbackDueDate;
    }

    public function getOverdueLevel(Permit $permit): int
    {
        if ($permit->getStatus() === PermitStatus::Bezahlt) {
            return 0;
        }

        $now = $this->clock->now();
        $userDeadline = $this->calculatePaymentDueDate($permit);

        $notifyDays = (int) $this->config->get('payment_due_days_notify', 2);
        $staffAlertThreshold = $userDeadline->modify("+{$notifyDays} days");

        if ($now > $staffAlertThreshold) {
            return 2;
        }

        if ($now > $userDeadline) {
            return 1;
        }

        return 0;
    }

    public function generateUsageText(Permit $permit): string
    {
        $pattern = (string) $this->config->get('usage_pattern', 'EFG-{{code}}-{{nachname}}');

        $shortCode = \substr($permit->code->value, -6);
        $nameParts = \explode(' ', $permit->getOwnerName());
        $vorname = $nameParts[0] ?? '';
        $nachname = $nameParts[\count($nameParts) - 1] ?? '';

        $replace = [
            '{{code}}' => $shortCode,
            '{{nachname}}' => $nachname,
            '{{vorname}}' => $vorname,
            '{{name}}' => $permit->getOwnerName(),
        ];

        return \str_replace(\array_keys($replace), \array_values($replace), $pattern);
    }
}
