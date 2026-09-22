<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Listeners;

use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrHandler;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrQuery;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitStatus;
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
        private GenerateEpcQrHandler $qrHandler,
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
        $geheimnis = (string) $this->config->get('geheimnis', '');
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
            $boardRecipients = \array_filter(\array_map('trim', \explode(',', (string) $boardRecipientsRaw)));

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
                'typLabel' => (function ($typ, $config) {
                    $vConfigs = $config->get('vehicle_types', []);

                    return $vConfigs[$typ]['label'] ?? 'Fahrzeug: ' . \strtoupper($typ);
                })($permit->getVehicleType(), $this->config),
                'vereinsName' => $this->config->get('vereins_name'),
                'von_formatted' => $permit->getValidFrom()->format('d.m.Y'),
                'zweck' => $permit->getPurpose(),
            ];

            foreach ($boardRecipients as $recipient) {
                if (\filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                    $this->mailService->sendTemplate(
                        recipient: $recipient,
                        subject: "[{$permitCodeStr}] - {$zeitraum} - {$permit->getOwnerName()}",
                        template: 'board_notification',
                        data: $data,
                        replyTo: $userEmail,
                    );
                }
            }
        }

        // --- MAIL AN NUTZER (Nur wenn E-Mail vorhanden ist) ---
        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        // --- 2. ZAHLUNGSAUFFORDERUNG ---
        if ($permit->getStatus() !== PermitStatus::Bezahlt) {
            $usage = $this->financialCalculator->generateUsageText($permit);
            $epcQrData = $this->qrHandler->handle(new GenerateEpcQrQuery($permit->getPrice(), $usage));

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
                    'iban' => $this->config->get('iban'),
                    'kontoinhaber' => $this->config->get('kontoinhaber'),
                    'name' => $permit->getOwnerName(),
                    'usage' => $usage,
                    'vereinsName' => $this->config->get('vereins_name'),
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
        $qrResult = $writer->write($qrCode);
        $checkQrBase64 = $qrResult->getDataUri(); // Gibt data:image/png;base64,... zurück

        $pdfData = [
            'bis_formatted' => $permit->getValidUntil()->format('d.m.Y'),
            'checkUrl' => $checkUrl,
            'checkQrBase64' => $checkQrBase64,
            'erstellt' => $permit->getCreatedAt()->format('d.m.Y H:i'),
            'firma' => $permit->getCompany() ?? '',
            'fullIdentifier' => $permitCodeStr,
            'holidayNotice' => $holidayNotice,
            'jahresFarbe' => $this->config->get('jahresFarbe'),
            'kennzeichen' => $permit->getLicensePlate(),
            'name' => $permit->getOwnerName(),
            'opening_html' => $opening,
            'parzelle' => $permit->getPlotNumber(),
            'settings' => ['base_url' => $safeBaseUrl],
            'template_key' => $permit->template_key->value,
            'terminkalenderUrl' => $this->config->get('terminkalender_url'),
            'vereinsName' => $this->config->get('vereins_name'),
            'von_formatted' => $permit->getValidFrom()->format('d.m.Y'),
            'zweck' => $permit->getPurpose(),
        ];

        // 3.2 HTML rendern und in PDF umwandeln
        $a4Html = $this->renderer->render('emails/permit_a4_document', $pdfData);
        $pdfBinary = $this->pdfGenerator->generateFromHtml($a4Html);

        // 3.3 Neue, kurze E-Mail versenden und PDF anhängen
        $this->mailService->sendTemplate(
            $permit->getOwnerEmail(),
            'Ausnahmegenehmigung: ' . $this->config->get('vereins_name') . ': ' . $permitCodeStr,
            'permit_approved_with_pdf',
            [
                'baseUrl' => $safeBaseUrl,
                'fullIdentifier' => $permitCodeStr,
                'name' => $permit->getOwnerName(),
                'vereinsName' => $this->config->get('vereins_name'),
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
