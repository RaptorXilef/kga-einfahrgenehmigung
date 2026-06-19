<?php

declare(strict_types=1);

namespace App\Application\Listener;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Event\MagicLinkRequestedEvent;

/**
 * Sendet die E-Mail mit dem Magic-Link und dem 6-stelligen OTP-Code an den Nutzer.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SendMagicLinkMailListener
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
    ) {
    }

    public function handle(MagicLinkRequestedEvent $event): void
    {
        $link = $this->config->getBaseUrl() . 'history.php?token=' . $event->token;

        $this->mailService->sendTemplate(
            $event->email,
            'Login-Code: Ihre Genehmigungen',
            'magic_link',
            [
                'baseUrl'     => $this->config->getBaseUrl(),
                'code'        => $event->code,
                'duration'    => $this->config->get('magic_link_duration'),
                'link'        => $link,
                'vereinsName' => $this->config->get('vereins_name'),
            ],
        );
    }
}
