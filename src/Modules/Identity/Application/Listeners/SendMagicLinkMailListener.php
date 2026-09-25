<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Modules\Identity\Domain\Events\MagicLinkRequestedEvent;

/**
 * Sendet die E-Mail mit dem Magic-Link und dem 6-stelligen OTP-Code an den Nutzer.
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
        // Garantiert einen sauberen Slash am Ende der URL und verweist auf die Token-Verifizierungsroute
        $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
        $link = $safeBaseUrl . 'history_verify_token?token=' . \urlencode($event->token);

        $this->mailService->sendTemplate(
            $event->email,
            'Login-Code: Ihre Genehmigungen',
            'magic_link',
            [
                'baseUrl' => $safeBaseUrl,
                'code' => $event->code,
                'duration' => $this->config->getInt('magic_link_duration', 15),
                'link' => $link,
                'vereinsName' => $this->config->getString('vereins_name'),
            ],
            null,
            100, // Hohe Priorität für Logins
        );
    }
}
