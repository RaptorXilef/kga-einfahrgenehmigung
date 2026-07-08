<?php

declare(strict_types=1);

namespace App\Application\Listener;

use App\Application\View\HolidayHtmlPresenter;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Entity\PermitStatus;
use App\Core\Event\PermitCreatedEvent;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;

/**
 * Lauscht auf PermitCreatedEvent und versendet die System-E-Mails.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SendPermitMailListener
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private MailServiceInterface $mailService,
        private PermitService $permitService,
    ) {
    }

    /**
     * TODO DOCBLOCK
     */
    public function handle(PermitCreatedEvent $event): void
    {
        $permit    = $event->permit;
        $shortCode = $event->shortCode;

        // FIX: Wert explizit entkapseln für native PHP String-Funktionen
        $permitCodeStr = $permit->code->value;

        $zeitraum = "{$permit->getValidFrom()->format('d.m.Y')} bis {$permit->getValidUntil()->format('d.m.Y')}";

        $geheimnis = (string) $this->config->get('geheimnis', '');
        $token     = \hash_hmac('sha256', $permitCodeStr, $geheimnis);

        $opening = HolidayHtmlPresenter::formatOpeningHours(
            $this->holidayService->getOpeningHoursDataForDateRange($permit->getValidFrom(), $permit->getValidUntil()),
        );
        $holidayNotice = HolidayHtmlPresenter::formatHolidayNotice(
            $this->holidayService->getHolidaysInRange($permit->getValidFrom(), $permit->getValidUntil()),
        );

        $mailConfig = $this->config->getMailSettings();

        // --- 1. MAIL AN VORSTAND ---
        if (($mailConfig['send_board_notification'] ?? true) === true) {
            $this->mailService->sendTemplate(
                recipient: $mailConfig['recipients'][$this->config->isTestMode() ? 'test' : 'live'],
                subject: "[{$permitCodeStr}] - {$zeitraum} - {$permit->getOwnerName()}",
                template: 'board_notification',
                data: [
                    'adminLink'      => $this->config->getBaseUrl() . "check.php?code={$permitCodeStr}&token={$token}",
                    'bis_formatted'  => $permit->getValidUntil()->format('d.m.Y'),
                    'email'          => $permit->getOwnerEmail() ?: 'Keine angegeben',
                    'firma'          => $permit->getCompany() ?? '',
                    'fullIdentifier' => $permitCodeStr,
                    'kennzeichen'    => $permit->getLicensePlate(),
                    'name'           => $permit->getOwnerName(),
                    'parzelle'       => $permit->getPlotNumber(),
                    'preis'          => \number_format($permit->getPrice(), 2, ',', '.') . ' €',
                    'typLabel'       => (function ($typ, $config) {
                        $vConfigs = $config->get('vehicle_types', []);

                        return $vConfigs[$typ]['label'] ?? 'Fahrzeug: ' . \strtoupper($typ);
                    })($permit->getVehicleType(), $this->config),
                    'vereinsName'   => $this->config->get('vereins_name'),
                    'von_formatted' => $permit->getValidFrom()->format('d.m.Y'),
                    'zweck'         => $permit->getPurpose(),
                ],
            );
        }

        // --- MAIL AN NUTZER (Nur wenn E-Mail vorhanden ist) ---
        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        // --- 2. ZAHLUNGSAUFFORDERUNG ---
        if ($permit->getStatus() !== PermitStatus::Bezahlt) {
            $nameParts = \explode(' ', $permit->getOwnerName());
            $vorname   = $nameParts[0] ?? 'Unbekannt';
            $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';
            $usage     = "EFG-{$nachname}-{$vorname}-{$shortCode}";

            $epcQrData = $this->bankQrGenerator->generate($permit->getPrice(), $usage);

            $this->mailService->sendTemplate(
                $permit->getOwnerEmail(),
                "Zahlung erforderlich: {$permitCodeStr}",
                'payment_request',
                [
                    'baseUrl'        => $this->config->getBaseUrl(),
                    'betrag'         => \number_format($permit->getPrice(), 2, ',', '.') . ' €',
                    'dueDate'        => $this->permitService->calculatePaymentDueDate($permit)->format('d.m.Y'),
                    'epcData'        => \urlencode($epcQrData),
                    'fullIdentifier' => $permitCodeStr,
                    'iban'           => $this->config->get('iban'),
                    'kontoinhaber'   => $this->config->get('kontoinhaber'),
                    'name'           => $permit->getOwnerName(),
                    'usage'          => $usage,
                    'vereinsName'    => $this->config->get('vereins_name'),
                ],
            );
        }

        // --- 3. DAS A4 DOKUMENT ---
        $this->mailService->sendTemplate(
            $permit->getOwnerEmail(),
            'Ausnahmegenehmigung: ' . $this->config->get('vereins_name') . ': ' . $permitCodeStr,
            'permit_a4_document',
            [
                'bis_formatted'     => $permit->getValidUntil()->format('d.m.Y'),
                'checkUrl'          => \urlencode($this->config->getBaseUrl() . 'check.php?code=' . $permitCodeStr),
                'erstellt'          => $permit->getCreatedAt()->format('d.m.Y H:i'),
                'firma'             => $permit->getCompany() ?? '',
                'fullIdentifier'    => $permitCodeStr,
                'holidayNotice'     => $holidayNotice,
                'jahresFarbe'       => $this->config->get('jahresFarbe'),
                'kennzeichen'       => $permit->getLicensePlate(),
                'opening_html'      => $opening,
                'parzelle'          => $permit->getPlotNumber(),
                'settings'          => ['base_url' => $this->config->getBaseUrl()],
                'template_key'      => $permit->template_key->value, // FIX: Auch Template-Key explizit auslesen
                'terminkalenderUrl' => $this->config->get('terminkalender_url'),
                'vereinsName'       => $this->config->get('vereins_name'),
                'von_formatted'     => $permit->getValidFrom()->format('d.m.Y'),
                'zweck'             => $permit->getPurpose(),
            ],
        );
    }
}
