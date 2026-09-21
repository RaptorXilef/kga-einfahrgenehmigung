<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Permit\Application\UseCases\SendPaymentReminders\SendPaymentRemindersCommand;
use App\Modules\Permit\Application\UseCases\SendPaymentReminders\SendPaymentRemindersHandler;

#[Route('GET', '/api/cron/reminders')]
#[Route('POST', '/api/cron/reminders')]
final readonly class RemindersCronAction implements ViewActionInterface
{
    public function __construct(
        private SendPaymentRemindersHandler $reminderHandler, // CQRS
        private ConfigInterface $config,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        if (($request->get['token'] ?? '') !== (string) $this->config->get('cron_secret', '')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        // Führt den Job für alle Permits aus (da $code = null)
        $this->reminderHandler->handle(new SendPaymentRemindersCommand());

        return JsonResponse::success([
            'message' => 'Zahlungserinnerungs-Prozess erfolgreich durchlaufen.',
        ]);
    }
}
