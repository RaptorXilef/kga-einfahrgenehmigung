<?php

declare(strict_types=1);

namespace App\Application\Listener;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Event\PaymentReminderEvent;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\PermitService;

final readonly class SendPaymentReminderMailListener
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
        private PermitService $permitService,
    ) {
    }

    public function handle(PaymentReminderEvent $event): void
    {
        $permit = $event->permit;

        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        // Bank-QR-Code nochmal generieren, um das Bezahlen direkt aus der Reminder-Mail zu erleichtern
        $shortCode = \substr($permit->code, -6);
        $nameParts = \explode(' ', $permit->getOwnerName());
        $vorname   = $nameParts[0] ?? 'Unbekannt';
        $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';
        $usage     = "EFG-{$nachname}-{$vorname}-{$shortCode}";

        $epcQrData = $this->bankQrGenerator->generate($permit->getPrice(), $usage);

        $this->mailService->sendTemplate(
            $permit->getOwnerEmail(),
            "Zahlungserinnerung: Ausnahmegenehmigung {$permit->code}",
            'payment_reminder',
            [
                'baseUrl'        => $this->config->getBaseUrl(),
                'betrag'         => \number_format($permit->getPrice(), 2, ',', '.') . ' €',
                'dueDate'        => $this->permitService->calculatePaymentDueDate($permit)->format('d.m.Y'),
                'epcData'        => \urlencode($epcQrData),
                'fullIdentifier' => $permit->code,
                'iban'           => $this->config->get('iban'),
                'kontoinhaber'   => $this->config->get('kontoinhaber'),
                'name'           => $permit->getOwnerName(),
                'usage'          => $usage,
                'vereinsName'    => $this->config->get('vereins_name'),
            ],
        );
    }
}
