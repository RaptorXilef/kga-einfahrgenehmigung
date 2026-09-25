<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
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

    /**
     * Ermittelt den Basispreis eines Templates für einen bestimmten Fahrzeugtyp.
     */
    public function calculateBasePrice(TemplateKey $templateKey, string $vehicleType): float
    {
        $templates = $this->config->getArray('permit_templates');
        $template = $templates[$templateKey->value] ?? null;
        if (!\is_array($template)) {
            return 0.0;
        }

        $vehicleTypes = $this->config->getArray('vehicle_types');
        $defaultType = $vehicleTypes === [] ? 'pkw' : (string) \array_key_first($vehicleTypes);

        $prices = \is_array($template['prices'] ?? null) ? $template['prices'] : [];
        $typeToUse = isset($prices[$vehicleType]) ? $vehicleType : $defaultType;
        $rawPrice = $prices[$typeToUse] ?? 0.0;

        return \is_numeric($rawPrice) ? (float) $rawPrice : 0.0;
    }

    public function calculatePaymentDueDate(Permit $permit): DateTimeImmutable
    {
        $dueDays = $this->config->getInt('payment_due_days', 14);
        $daysBeforeValidity = $this->config->getInt('payment_due_days_before_validity', 2);

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

        $notifyDays = $this->config->getInt('payment_due_days_notify', 2);
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
        $pattern = $this->config->getString('usage_pattern', 'EFG-{{code}}-{{nachname}}');

        $codeParts = \explode('-', $permit->code->value);
        $shortCode = (string) \end($codeParts);

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
