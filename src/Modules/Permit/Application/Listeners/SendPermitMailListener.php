<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Listeners;

use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\FinanceIntegrationInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Contracts\System\QrCodeGeneratorInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\Modules\Permit\Presentation\View\PermitA4Presenter;

/**
 * Lauscht auf PermitCreatedEvent und versendet die System-E-Mails
 * (Vorstands-Benachrichtigung sowie die kombinierte Genehmigungs- & Zahlungs-E-Mail inkl. PDF-Anhang).
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
        private QrCodeGeneratorInterface $qrCodeGenerator,
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
        $vereinsName = $this->config->getString('vereins_name');

        // --- 1. MAIL AN VORSTAND ---
        if (($mailConfig['send_board_notification'] ?? true) === true) {
            $userEmail = $permit->getOwnerEmail() !== '' ? $permit->getOwnerEmail() : null;

            $boardRecipientsRaw = $mailConfig['board_recipients'] ?? '';
            $boardRecipients = \array_filter(\array_map(trim(...), \explode(',', (string) $boardRecipientsRaw)));

            $vConfigs = $this->config->getArray('vehicle_types');
            $typ = $permit->getVehicleType();
            $typLabel = (string) ($vConfigs[$typ]['label'] ?? 'Fahrzeug: ' . \strtoupper($typ));

            $boardData = [
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
                'vereinsName' => $vereinsName,
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
                    data: $boardData,
                    replyTo: $userEmail,
                );
            }
        }

        // --- 2. KOMBINIERTE GENEHMIGUNGS- & ZAHLUNGSMAIL AN NUTZER ---
        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        // 2.1 A4-PDF-Dokument als Anhang generieren
        $checkQrBase64 = $this->qrCodeGenerator->generateDataUri($checkUrl, 160, 0);

        $docDto = PermitA4Presenter::createViewDto(
            fullIdentifier: $permitCodeStr,
            templateKey: $permit->template_key->value,
            jahresFarbe: $this->config->getString('jahresFarbe'),
            vereinsName: $vereinsName,
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

        $a4Html = $this->renderer->render('emails/permit_a4_document', ['docDto' => $docDto]);
        $pdfBinary = $this->pdfGenerator->generateFromHtml($a4Html);

        // 2.2 Zahlungsdaten & Texte logikfrei vorbereiten
        $isPaymentRequired = $permit->getStatus() !== PermitStatus::Bezahlt && $permit->getPrice() > 0.0;
        $requirePaymentForValidity = $this->config->getBool('require_payment_for_validity', true);

        $betrag = '';
        $dueDate = '';
        $epcData = '';
        $iban = '';
        $bic = '';
        $kontoinhaber = '';
        $usage = '';
        $paymentValidityNotice = '';

        if ($isPaymentRequired) {
            $usage = $this->financialCalculator->generateUsageText($permit);
            $epcQrData = $this->financeIntegration->generateEpcQrData($permit->getPrice(), $usage);

            $betrag = \number_format($permit->getPrice(), 2, ',', '.') . ' €';
            $dueDate = $this->financialCalculator->calculatePaymentDueDate($permit)->format('d.m.Y');
            $epcData = \urlencode($epcQrData);
            $iban = $this->config->getString('iban');
            $bic = $this->config->getString('bic');
            $kontoinhaber = $this->config->getString('kontoinhaber');
            $paymentValidityNotice = $requirePaymentForValidity
                ? 'Wichtig: Ihre Genehmigung wird erst nach vollständigem Zahlungseingang für Kontrollen freigeschaltet.'
                : 'Bitte stellen Sie sicher, dass der Betrag fristgerecht auf unserem Konto eingeht.';

            $subject = "Ausnahmegenehmigung & Zahlungsinfo: {$vereinsName}: {$permitCodeStr}";
            $headline = 'Ihre Ausnahmegenehmigung & Zahlungsinformationen';
            $introStatusText = 'wurde registriert. Das offizielle Genehmigungsdokument finden Sie bereits als PDF im Anhang dieser E-Mail.';
        } else {
            $subject = "Ausnahmegenehmigung: {$vereinsName}: {$permitCodeStr}";
            $headline = 'Ihre Genehmigung ist aktiv';
            $introStatusText = 'ist gültig und wurde vom System aktiviert. Das offizielle Genehmigungsdokument finden Sie als PDF im Anhang dieser E-Mail.';
        }

        // 2.3 Eine einzige konsolidierte E-Mail inkl. PDF-Anhang versenden
        $this->mailService->sendTemplate(
            recipient: $permit->getOwnerEmail(),
            subject: $subject,
            template: 'permit_approved_with_pdf',
            data: [
                'baseUrl' => $safeBaseUrl,
                'fullIdentifier' => $permitCodeStr,
                'name' => $permit->getOwnerName(),
                'vereinsName' => $vereinsName,
                'headline' => $headline,
                'introStatusText' => $introStatusText,
                'isPaymentRequired' => $isPaymentRequired,
                'betrag' => $betrag,
                'dueDate' => $dueDate,
                'epcData' => $epcData,
                'iban' => $iban,
                'bic' => $bic,
                'kontoinhaber' => $kontoinhaber,
                'usage' => $usage,
                'paymentValidityNotice' => $paymentValidityNotice,
            ],
            replyTo: null,
            priority: 50,
            attachments: [
                [
                    'name' => "Genehmigung_{$permitCodeStr}.pdf",
                    'mime' => 'application/pdf',
                    'content' => $pdfBinary,
                ],
            ],
        );
    }
}
