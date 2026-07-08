<?php

declare(strict_types=1);

namespace App\Application\Listener;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Event\PermitCancelledEvent;

final readonly class SendPermitCancelledMailListener
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
    ) {
    }

    public function handle(PermitCancelledEvent $event): void
    {
        $permit = $event->permit;

        if (\in_array(\trim($permit->getOwnerEmail()), ['', '0'], true)) {
            return;
        }

        $permitCodeStr = $permit->code->value; // FIX

        $this->mailService->sendTemplate(
            $permit->getOwnerEmail(),
            "Stornierungsbestätigung: {$permitCodeStr}",
            'permit_cancelled',
            [
                'baseUrl'        => $this->config->getBaseUrl(),
                'fullIdentifier' => $permitCodeStr,
                'name'           => $permit->getOwnerName(),
                'vereinsName'    => $this->config->get('vereins_name'),
            ],
        );
    }
}
