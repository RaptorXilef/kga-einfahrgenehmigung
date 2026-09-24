<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SendPaymentReminders;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use Override;

#[Route('GET', '/api/cron/reminders')]
#[Route('POST', '/api/cron/reminders')]
final readonly class RemindersCronAction implements ViewActionInterface
{
    public function __construct(
        private SendPaymentRemindersHandler $reminderHandler,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if (($request->get['token'] ?? '') !== $this->config->getString('cron_secret')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        $this->reminderHandler->handle(new SendPaymentRemindersCommand());

        return JsonResponse::success([
            'message' => 'Zahlungserinnerungs-Prozess erfolgreich durchlaufen.',
        ]);
    }
}
