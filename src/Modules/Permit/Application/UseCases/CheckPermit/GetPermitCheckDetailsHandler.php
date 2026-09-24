<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CheckPermit;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;

/**
 * @implements QueryHandlerInterface<GetPermitCheckDetailsQuery, PermitCheckDetailsDto>
 */
final readonly class GetPermitCheckDetailsHandler implements QueryHandlerInterface
{
    public function __construct(
        private GetPermitByCodeHandler $getPermitByCodeHandler, // CQRS statt PermitService
        private PermitRepositoryInterface $repository,
        private HolidayService $holidayService,
        private ConfigInterface $config,
        private ClockInterface $clock, // VSA FIX: Inject ClockInterface
    ) {
    }

    /**
     * @param GetPermitCheckDetailsQuery $query
     */
    public function handle(mixed $query): PermitCheckDetailsDto
    {
        $now = $this->clock->now();

        // 1. Genehmigung suchen (Zuerst via Code in allen Tabellen)
        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($query->codeOrPlate));

        if (!$permit instanceof Permit) {
            // Fallback auf Kennzeichensuche im aktiven Storage
            $permit = $this->repository->findByLicensePlate($query->codeOrPlate);

            if (!$permit instanceof Permit) {
                return $this->createNotFoundDto();
            }
        }

        // 2. Rechte prüfen
        $showAdminView = $query->isAdminAuth;
        if (!$showAdminView) {
            $secret = (string) $this->config->get('geheimnis', '');
            if ($secret !== '') {
                $expected = \hash_hmac('sha256', $permit->code->value, $secret);
                $showAdminView = \hash_equals($expected, $query->token);
            }
        }

        // 3. Status & Zeiten berechnen
        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);
        $isDateValid = $permit->isValid($requirePayment);
        $isTimeAllowed = $this->holidayService->isTimeAllowedNow();

        $pageStateClass = 'is-state-error';
        $statusColorClass = 'u-color-muted';
        $statusIcon = 'warning.webp';
        $statusHeadline = 'GENEHMIGUNG UNGÜLTIG';
        $statusSubTextHtml = '<p>Zahlung offen, Zeitraum abgelaufen oder noch nicht erreicht.</p>';

        if ($permit->isSuspended()) {
            $pageStateClass = 'is-state-error';
            $statusColorClass = 'u-color-danger';
            $statusIcon = 'error.webp'; // denied.webp im public view
            $statusHeadline = 'ZUTRITT VERWEIGERT';
            $statusSubTextHtml = '<p class="u-font-semibold u-margin-block-none">Diese Genehmigung ist gesperrt/widerrufen.</p>';

            if (!$showAdminView && $permit->getSuspensionReason() !== null && $permit->getSuspensionReason() !== '') {
                $reason = \htmlspecialchars($permit->getSuspensionReason());
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
                if ($nextSlot > $permit->getValidUntil()) {
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
        if ($permit->getOwnerEmail() !== '') {
            $safeMail = \htmlspecialchars($permit->getOwnerEmail(), \ENT_QUOTES, 'UTF-8');
            $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
            $emailHtml = '<a href="mailto:' . $safeMail . '" class="u-text-link c-table__mail-link">' . $wbrMail . '</a>';
        }

        // 5. Finanz-Status
        $isPaid = $permit->isPaid();
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
            code: $permit->code->value,
            ownerName: $permit->getOwnerName(),
            plotNumber: $permit->getPlotNumber(),
            emailHtml: $emailHtml,
            purpose: $permit->getPurpose(),
            vehicleType: \strtoupper($permit->getVehicleType()),
            licensePlate: $permit->getLicensePlate(),
            company: $permit->getCompany(),
            validityPeriod: $permit->getValidFrom()->format('d.m.Y') . ' bis ' . $permit->getValidUntil()->format('d.m.Y'),
            openingHoursHtml: HolidayHtmlPresenter::formatOpeningHours($this->holidayService->getOpeningHoursDataForDateRange($permit->getValidFrom(), $permit->getValidUntil())),
            holidayNoticeHtml: HolidayHtmlPresenter::formatHolidayNotice($this->holidayService->getHolidaysInRange($permit->getValidFrom(), $permit->getValidUntil())),
            priceFormatted: \number_format($permit->getPrice(), 2, ',', '.') . ' €',
            createdAtFormatted: $permit->getCreatedAt()->format('d.m.Y H:i') . ' Uhr',
            financeStatusText: $financeStatusText,
            financeStatusClass: $financeStatusClass,
            isSuspended: $permit->isSuspended(),
            suspensionReason: $permit->getSuspensionReason(),
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
