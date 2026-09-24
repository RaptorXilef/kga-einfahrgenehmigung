<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Listeners;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\FinanceIntegrationInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Modules\Permit\Domain\Events\PaymentReminderEvent;
use App\Modules\Permit\Domain\PermitFinancialCalculator;

final readonly class SendPaymentReminderMailListener
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
        private PermitFinancialCalculator $financialCalculator,
        private FinanceIntegrationInterface $financeIntegration,
    ) {
    }

    public function handle(PaymentReminderEvent $event): void
    {
        $permit = $event->permit;

        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        $permitCodeStr = $permit->code->value;
        $usage = $this->financialCalculator->generateUsageText($permit);

        // Bank-QR-Code nochmal generieren, um das Bezahlen direkt aus der Reminder-Mail zu erleichtern
        $epcQrData = $this->financeIntegration->generateEpcQrData($permit->getPrice(), $usage);

        // Garantiert einen sauberen Slash am Ende der URL, damit der QR-Code-Endpoint erreicht wird
        $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        $this->mailService->sendTemplate(
            $permit->getOwnerEmail(),
            "Zahlungserinnerung: Ausnahmegenehmigung {$permitCodeStr}",
            'payment_reminder',
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
            null,
            10, // Niedrigste Priorität für Erinnerungen
        );
    }
}
