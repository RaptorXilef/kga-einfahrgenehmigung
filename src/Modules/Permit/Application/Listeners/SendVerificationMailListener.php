<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Listeners;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Modules\Permit\Domain\Events\VerificationRequestedEvent;

/**
 * Sendet die Double-Opt-In-Verifizierungsmail an den Antragsteller.
 */
final readonly class SendVerificationMailListener
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
    ) {
    }

    public function handle(VerificationRequestedEvent $event): void
    {
        // Garantiert einen sauberen Slash am Ende der URL, damit verify?token korrekt generiert wird
        $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        $this->mailService->sendTemplate(
            (string) $event->data['email'],
            "E-Mail bestätigen: {$event->shortCode}",
            'verify_email',
            [
                'baseUrl' => $safeBaseUrl,
                'code' => $event->shortCode,
                'name' => (string) $event->data['name'],
                'vereinsName' => $this->config->get('vereins_name'),
                'verifyUrl' => $safeBaseUrl . 'verify?token=' . $event->token,
            ],
            null,
            100, // Hohe Priorität für Verifizierungen
        );
    }
}
