<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Listeners;

use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\FinanceIntegrationInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\Modules\Permit\Presentation\View\PermitA4Presenter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Lauscht auf PermitCreatedEvent und versendet die System-E-Mails.
 */
final readonly class SendPermitMailListener
{
    public function __construct(
        private FinanceIntegrationInterface $financeIntegration,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private MailServiceInterface $mailService,
        private PermitFinancialCalculator $financialCalculator,
        private PdfGeneratorInterface $pdfGenerator,
        private TemplateRenderer $renderer,
    ) {
    }

    public function handle(PermitCreatedEvent $event): void
    {
        $permit = $event->permit;
        $permitCodeStr = $permit->code->value;

        $zeitraum = "{$permit->getValidFrom()->format('d.m.Y')} bis {$permit->getValidUntil()->format('d.m.Y')}";
        $geheimnis = $this->config->getString('geheimnis');
        $token = \hash_hmac('sha256', $permitCodeStr, $geheimnis);

        $opening = HolidayHtmlPresenter::formatOpeningHours(
            $this->holidayService->getOpeningHoursDataForDateRange(
                $permit->getValidFrom(),
                $permit->getValidUntil(),
            ),
        );

        $holidayNotice = HolidayHtmlPresenter::formatHolidayNotice(
            $this->holidayService->getHolidaysInRange(
                $permit->getValidFrom(),
                $permit->getValidUntil(),
            ),
        );

        $mailConfig = $this->config->getMailSettings();
        // Sichere Base-URL mit garantiert einem abschließenden Slash
        $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
        $checkUrl = $safeBaseUrl . 'check?code=' . $permitCodeStr;

        // --- 1. MAIL AN VORSTAND ---
        if (($mailConfig['send_board_notification'] ?? true) === true) {
            $userEmail = $permit->getOwnerEmail() !== '' ? $permit->getOwnerEmail() : null;

            $boardRecipientsRaw = $mailConfig['board_recipients'] ?? '';
            $boardRecipients = \array_filter(\array_map(trim(...), \explode(',', (string) $boardRecipientsRaw)));

            $vConfigs = $this->config->getArray('vehicle_types');
            $typ = $permit->getVehicleType();
            $typLabel = (string) ($vConfigs[$typ]['label'] ?? ('Fahrzeug: ' . \strtoupper($typ)));

            $data = [
                'adminLink' => $checkUrl . "&token={$token}",
                'bis_formatted' => $permit->getValidUntil()->format('d.m.Y'),
                'email' => $permit->getOwnerEmail() ?: 'Keine angegeben',
                'firma' => $permit->getCompany() ?? '',
                'fullIdentifier' => $permitCodeStr,
                'kennzeichen' => $permit->getLicensePlate(),
                'name' => $permit->getOwnerName(),
                'parzelle' => $permit->getPlotNumber(),
                'preis' => \number_format($permit->getPrice(), 2, ',', '.') . ' €',
                'typLabel' => $typLabel,
                'vereinsName' => $this->config->getString('vereins_name'),
                'von_formatted' => $permit->getValidFrom()->format('d.m.Y'),
                'zweck' => $permit->getPurpose(),
            ];

            foreach ($boardRecipients as $recipient) {
                if (!\filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $this->mailService->sendTemplate(
                    recipient: $recipient,
                    subject: "[{$permitCodeStr}] - {$zeitraum} - {$permit->getOwnerName()}",
                    template: 'board_notification',
                    data: $data,
                    replyTo: $userEmail,
                );
            }
        }

        // --- MAIL AN NUTZER (Nur wenn E-Mail vorhanden ist) ---
        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        // --- 2. ZAHLUNGSAUFFORDERUNG ---
        if ($permit->getStatus() !== PermitStatus::Bezahlt) {
            $usage = $this->financialCalculator->generateUsageText($permit);
            $epcQrData = $this->financeIntegration->generateEpcQrData($permit->getPrice(), $usage);

            $this->mailService->sendTemplate(
                $permit->getOwnerEmail(),
                "Zahlung erforderlich: {$permitCodeStr}",
                'payment_request',
                [
                    'baseUrl' => $safeBaseUrl,
                    'betrag' => \number_format($permit->getPrice(), 2, ',', '.') . ' €',
                    'dueDate' => $this->financialCalculator->calculatePaymentDueDate($permit)->format('d.m.Y'),
                    'epcData' => \urlencode($epcQrData),
                    'fullIdentifier' => $permitCodeStr,
                    'iban' => $this->config->getString('iban'),
                    'kontoinhaber' => $this->config->getString('kontoinhaber'),
                    'name' => $permit->getOwnerName(),
                    'usage' => $usage,
                    'vereinsName' => $this->config->getString('vereins_name'),
                ],
            );
        }

        // --- 3. DAS A4 DOKUMENT (ALS PDF ANHANG) ---

        // 3.1 QR-Code für den Anhang in Base64 generieren (Offline-Sicherheit)
        $qrCode = new QrCode(
            data: $checkUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 160,
            margin: 0,
        );
        $writer = new PngWriter();
        $checkQrBase64 = $writer->write($qrCode)->getDataUri();

        $docDto = PermitA4Presenter::createViewDto(
            fullIdentifier: $permitCodeStr,
            templateKey: $permit->template_key->value,
            jahresFarbe: $this->config->getString('jahresFarbe'),
            vereinsName: $this->config->getString('vereins_name'),
            checkQrBase64: $checkQrBase64,
            openingHtml: $opening,
            holidayNoticeHtml: $holidayNotice,
            name: $permit->getOwnerName(),
            validFrom: $permit->getValidFrom(),
            validUntil: $permit->getValidUntil(),
            kennzeichen: $permit->getLicensePlate(),
            firma: $permit->getCompany() ?? '',
            parzelle: $permit->getPlotNumber(),
            zweck: $permit->getPurpose(),
            terminkalenderUrl: $this->config->getString('terminkalender_url'),
            erstelltFormatted: $permit->getCreatedAt()->format('d.m.Y H:i'),
            baseUrl: $safeBaseUrl,
        );

        // 3.2 HTML rendern und in PDF umwandeln
        $a4Html = $this->renderer->render('emails/permit_a4_document', ['docDto' => $docDto]);
        $pdfBinary = $this->pdfGenerator->generateFromHtml($a4Html);

        // 3.3 Neue, kurze E-Mail versenden und PDF anhängen
        $this->mailService->sendTemplate(
            $permit->getOwnerEmail(),
            'Ausnahmegenehmigung: ' . $this->config->getString('vereins_name') . ': ' . $permitCodeStr,
            'permit_approved_with_pdf',
            [
                'baseUrl' => $safeBaseUrl,
                'fullIdentifier' => $permitCodeStr,
                'name' => $permit->getOwnerName(),
                'vereinsName' => $this->config->getString('vereins_name'),
            ],
            null,
            50,
            [
                [
                    'name' => "Genehmigung_{$permitCodeStr}.pdf",
                    'mime' => 'application/pdf',
                    'content' => $pdfBinary,
                ],
            ],
        );
    }
}
