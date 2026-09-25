<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CheckPermit;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Override;

/**
 * Bereitet die Daten für die QR-Code- und Manuelle Prüf-Ansicht (/check) auf.
 * VSA CQRS FIX: Arbeitet zu 100% auf dem flachen PermitReadDto (ohne Permit-Entity oder Repository).
 *
 * @implements QueryHandlerInterface<GetPermitCheckDetailsQuery, PermitCheckDetailsDto>
 */
final readonly class GetPermitCheckDetailsHandler implements QueryHandlerInterface
{
    public function __construct(
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private HolidayService $holidayService,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetPermitCheckDetailsQuery $query
     */
    #[Override]
    public function handle(mixed $query): PermitCheckDetailsDto
    {
        $now = $this->clock->now();

        // 1. Genehmigung via Read-Model suchen (inkl. Kennzeichen-Fallback im aktiven Bestand)
        $permit = $this->getPermitByCodeHandler->handle(
            new GetPermitByCodeQuery($query->codeOrPlate, allowLicensePlateFallback: true),
        );

        if (!$permit instanceof PermitReadDto) {
            return $this->createNotFoundDto();
        }

        // 2. Rechte prüfen
        $showAdminView = $query->isAdminAuth;
        if (!$showAdminView) {
            $secret = $this->config->getString('geheimnis');
            if ($secret !== '') {
                $expected = \hash_hmac('sha256', $permit->code, $secret);
                $showAdminView = \hash_equals($expected, $query->token);
            }
        }

        // 3. Status & Zeiten berechnen (inkl. ClockInterface-Injection)
        $requirePayment = $this->config->getBool('require_payment_for_validity', false);
        $isDateValid = $permit->isCurrentlyValid($requirePayment, $now);
        $isTimeAllowed = $this->holidayService->isTimeAllowedNow();

        $pageStateClass = 'is-state-error';
        $statusColorClass = 'u-color-muted';
        $statusIcon = 'warning.webp';
        $statusHeadline = 'GENEHMIGUNG UNGÜLTIG';
        $statusSubTextHtml = '<p>Zahlung offen, Zeitraum abgelaufen oder noch nicht erreicht.</p>';

        if ($permit->isSuspended) {
            $pageStateClass = 'is-state-error';
            $statusColorClass = 'u-color-danger';
            $statusIcon = 'error.webp';
            $statusHeadline = 'ZUTRITT VERWEIGERT';
            $statusSubTextHtml = '<p class="u-font-semibold u-margin-block-none">Diese Genehmigung ist gesperrt/widerrufen.</p>';

            if (!$showAdminView && $permit->suspensionReason !== null && $permit->suspensionReason !== '') {
                $reason = \htmlspecialchars($permit->suspensionReason);
                $statusSubTextHtml .= <<<HTML
                    <div class="c-box c-box--danger-soft u-margin-block-start-m u-text-center">
                        <strong class="u-text-xs u-text-uppercase u-letter-spacing-sm">Grund der Sperrung:</strong><br>
                        <span class="u-text-lg u-display-block u-margin-block-start-xs">{$reason}</span>
                    </div>
                    HTML;
            }
        } elseif ($isDateValid && $isTimeAllowed) {
            $pageStateClass = 'is-state-valid';
            $statusColorClass = 'u-color-success';
            $statusIcon = 'success.webp';
            $statusHeadline = 'GENEHMIGUNG GÜLTIG';
            $statusSubTextHtml = '';
        } elseif ($isDateValid && !$isTimeAllowed) {
            $pageStateClass = 'is-state-rest';
            $statusColorClass = 'u-color-warning';
            $statusIcon = 'hourglass.webp';
            $statusHeadline = 'AKTUELL RUHEZEIT';

            $nextSlot = $this->holidayService->getNextAvailableSlot($now);
            $nextText = 'Keine weitere Einfahrt möglich.';

            if ($nextSlot instanceof DateTimeImmutable) {
                if ($nextSlot > $permit->validUntil->setTime(23, 59, 59)) {
                    $nextText = 'Die Gültigkeit endet, bevor die Anlage wieder befahren werden darf.';
                } else {
                    $datePart = $nextSlot->format('d.m.Y');
                    $today = $now->format('d.m.Y');
                    $tomorrow = $now->modify('+1 day')->format('d.m.Y');

                    if ($datePart === $today) {
                        $nextText = 'heute ab ' . $nextSlot->format('H:i') . ' Uhr';
                    } elseif ($datePart === $tomorrow) {
                        $nextText = 'morgen ab ' . $nextSlot->format('H:i') . ' Uhr';
                    } else {
                        $nextText = 'am ' . $datePart . ' ab ' . $nextSlot->format('H:i') . ' Uhr';
                    }
                }
            }

            if (\str_contains($nextText, 'Gültigkeit')) {
                $statusSubTextHtml = '<p class="u-margin-block-none u-font-semibold">' . $nextText . '</p>';
            } else {
                $statusSubTextHtml = '<p class="u-margin-block-none">Nächste Einfahrt möglich:<br><strong class="u-font-semibold">' . $nextText . '</strong></p>';
            }
        }

        // 4. E-Mail HTML sicher generieren
        $emailHtml = '';
        if ($permit->ownerEmail !== '') {
            $safeMail = \htmlspecialchars($permit->ownerEmail, \ENT_QUOTES, 'UTF-8');
            $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
            $emailHtml = '<a href="mailto:' . $safeMail . '" class="u-text-link c-table__mail-link">' . $wbrMail . '</a>';
        }

        // 5. Finanz-Status
        $isPaid = $permit->isPaid;
        $financeStatusText = $isPaid ? 'BEZAHLT' : 'OFFEN';
        $financeStatusClass = $isPaid ? 'success' : 'warning';

        return new PermitCheckDetailsDto(
            isFound: true,
            showAdminView: $showAdminView,
            pageStateClass: $pageStateClass,
            statusColorClass: $statusColorClass,
            statusIcon: $statusIcon,
            statusHeadline: $statusHeadline,
            statusSubTextHtml: $statusSubTextHtml,
            code: $permit->code,
            ownerName: $permit->ownerName,
            plotNumber: $permit->plotNumber,
            emailHtml: $emailHtml,
            purpose: $permit->purpose,
            vehicleType: \strtoupper($permit->vehicleType),
            licensePlate: $permit->licensePlate,
            company: $permit->company,
            validityPeriod: $permit->validFromFormatted . ' bis ' . $permit->validUntilFormatted,
            openingHoursHtml: HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($permit->validFrom, $permit->validUntil),
            ),
            holidayNoticeHtml: HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($permit->validFrom, $permit->validUntil),
            ),
            priceFormatted: $permit->priceFormatted,
            createdAtFormatted: $permit->createdAtFormatted . ' Uhr',
            financeStatusText: $financeStatusText,
            financeStatusClass: $financeStatusClass,
            isSuspended: $permit->isSuspended,
            suspensionReason: $permit->suspensionReason,
            isPaid: $isPaid,
        );
    }

    private function createNotFoundDto(): PermitCheckDetailsDto
    {
        return new PermitCheckDetailsDto(
            isFound: false,
            showAdminView: false,
            pageStateClass: 'is-state-error',
            statusColorClass: 'u-color-muted',
            statusIcon: 'warning.webp',
            statusHeadline: 'GENEHMIGUNG UNGÜLTIG',
            statusSubTextHtml: '<p>Zahlung offen, Zeitraum abgelaufen oder noch nicht erreicht.</p>',
            code: '',
            ownerName: '',
            plotNumber: '',
            emailHtml: '',
            purpose: '',
            vehicleType: '',
            licensePlate: '',
            company: null,
            validityPeriod: '',
            openingHoursHtml: '',
            holidayNoticeHtml: '',
            priceFormatted: '',
            createdAtFormatted: '',
            financeStatusText: '',
            financeStatusClass: '',
            isSuspended: false,
            suspensionReason: null,
            isPaid: false,
        );
    }
}
