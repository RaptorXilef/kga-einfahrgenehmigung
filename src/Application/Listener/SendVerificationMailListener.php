<?php

declare(strict_types=1);

namespace App\Application\Listener;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Event\VerificationRequestedEvent;

/**
 * Sendet die Double-Opt-In-Verifizierungsmail an den Antragsteller.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
        $this->mailService->sendTemplate(
            (string) $event->data['email'],
            "E-Mail bestätigen: {$event->shortCode}",
            'verify_email',
            [
                'baseUrl'     => $this->config->getBaseUrl(),
                'code'        => $event->shortCode,
                'name'        => (string) $event->data['name'],
                'vereinsName' => $this->config->get('vereins_name'),
                'verifyUrl'   => $this->config->getBaseUrl() . 'verify.php?token=' . $event->token,
            ],
        );
    }
}
