<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Mail\MailQueueService;

/**
 * Action zum manuellen Anstoßen der Mail-Warteschlange.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemProcessMailQueueAction implements ViewActionInterface
{
    public function __construct(private MailServiceInterface $mailService)
    {
    }

    public function execute(array $requestData): mixed
    {
        // Wir prüfen, ob der injizierte Service die Queue-Funktion überhaupt besitzt
        if ($this->mailService instanceof MailQueueService) {
            $this->mailService->processQueue(10);
            JsonResponse::success(['status' => 'processed']);
        } else {
            // Falls das System z.B. nur synchron sendet (SmtpMailService)
            JsonResponse::success(['status' => 'skipped_no_queue']);
        }

        return null;
    }
}
