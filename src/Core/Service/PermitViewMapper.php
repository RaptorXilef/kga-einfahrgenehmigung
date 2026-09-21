<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Core\Entity\Permit;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto;
use DateTimeImmutable;

/**
 * Bridge-Service: Mappt komplexe Domain-Entities in dumme, flache View-DTOs.
 * Wird obsolet, sobald das Dashboard vollständig auf PDO umgestellt ist.
 */
final readonly class PermitViewMapper
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function mapToDashboardDto(Permit $permit, DateTimeImmutable $now, bool $requirePayment): DashboardPermitDto
    {
        $isSuspended = $permit->isSuspended();
        $isExpired = $permit->isExpired($now);
        $isFuture = $permit->isFuture($now);
        $isValid = $permit->isValid($requirePayment);

        // 1. CSS & Layout Logik
        $rowClass = $isSuspended ? 'c-table__row--danger' : '';
        if ($isExpired) {
            $rowClass = 'u-opacity-75';
        }

        // 2. Fahrzeug Icons
        $vKey = $permit->getVehicleType();
        $vCfg = $this->config->get('vehicle_types')[$vKey] ?? null;
        $vehicleIcon = $vCfg['icon'] ?? 'assets/img/icons/warning.webp';
        $vehicleLabel = $vCfg['label'] ?? 'Ehem. ' . \strtoupper($vKey);

        // 3. E-Mail Formatierung
        $emailHtml = '<small class="u-color-muted"><em>keine Angabe</em></small>';
        if ($permit->getOwnerEmail() !== '') {
            $safeMail = \htmlspecialchars($permit->getOwnerEmail(), \ENT_QUOTES, 'UTF-8');
            $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
            $emailHtml = <<<HTML
                <div class="u-flex u-align-center u-gap-xs">
                    <img src="assets/img/icons/envelope.webp" class="c-icon c-icon--inline" loading="lazy" alt="">
                    <small><a href="mailto:{$safeMail}" class="u-text-link c-table__mail-link">{$wbrMail}</a></small>
                </div>
                HTML;
        }

        // 4. Countdown Logik
        $countdownText = '';
        $countdownBadgeClass = 'c-badge--primary';

        if ($isFuture) {
            $daysUntil = (int) $now->diff($permit->getValidFrom())->format('%r%a');
            $countdownText = ($daysUntil === 1) ? 'Morgen' : "In {$daysUntil} Tagen";
        } elseif (!$isExpired) {
            $remaining = (int) $now->diff($permit->getValidUntil())->format('%r%a');
            $countdownText = ($remaining === 0) ? 'Läuft heute ab' : "Noch {$remaining} Tage";
            $countdownBadgeClass = ($remaining <= 1) ? 'c-badge--danger' : 'c-badge--primary';
        }

        // 5. Status Badges
        $statusBadgeHtml = '';
        if ($isSuspended) {
            $reason = \htmlspecialchars($permit->getSuspensionReason() ?? '');
            $statusBadgeHtml = "<span class=\"c-badge c-badge--danger\" title=\"{$reason}\">GESPERRT</span>";
        } elseif (!$isValid && !$isFuture && !$isExpired) {
            $statusBadgeHtml = '<span class="c-badge c-badge--warning" title="Zeitraum gültig, aber Zahlung ausstehend">UNBEZAHLT</span>';
        } elseif ($isValid) {
            $statusBadgeHtml = '<span class="c-badge c-badge--success">AKTIV</span>';
        }

        return new DashboardPermitDto(
            code: $permit->code->value,
            ownerName: $permit->getOwnerName(),
            plotNumber: $permit->getPlotNumber(),
            licensePlate: $permit->getLicensePlate() ?: '---',
            vehicleIcon: $vehicleIcon,
            vehicleLabel: $vehicleLabel,
            emailHtml: $emailHtml,
            validFromDate: $permit->getValidFrom()->format('d.m.Y'),
            validUntilDate: $permit->getValidUntil()->format('d.m.Y'),
            createdAtDate: $permit->getCreatedAt()->format('d.m.Y'),
            createdAtTime: $permit->getCreatedAt()->format('H:i'),
            priceFormatted: \number_format($permit->getPrice(), 2, ',', '.') . ' €',
            rowClass: $rowClass,
            countdownText: $countdownText,
            countdownBadgeClass: $countdownBadgeClass,
            statusBadgeHtml: $statusBadgeHtml,
            isSuspended: $isSuspended,
            suspendIcon: $isSuspended ? 'unlock.webp' : 'denied.webp',
            suspendTitle: $isSuspended ? 'Wieder freigeben' : 'Sperren',
            suspendActionUrl: $isSuspended ? 'unsuspend_permit' : 'suspend_permit',
            suspensionReason: $permit->getSuspensionReason(),
        );
    }
}
