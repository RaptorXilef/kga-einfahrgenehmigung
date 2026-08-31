<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

#[Route('GET', '/api/cron/reminders')]
#[Route('POST', '/api/cron/reminders')]
final readonly class RemindersCronAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private ConfigInterface $config,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        if (($request->get['token'] ?? '') !== (string) $this->config->get('cron_secret', '')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        $sentCount = $this->permitService->sendPaymentReminders();

        return JsonResponse::success([
            'message' => 'Zahlungserinnerungen erfolgreich versendet.',
            'sent_emails' => $sentCount,
        ]);
    }
}
