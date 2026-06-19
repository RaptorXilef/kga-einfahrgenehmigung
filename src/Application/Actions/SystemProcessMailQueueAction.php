<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Mail\MailServiceInterface;

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
        $this->mailService->processQueue(10);
        JsonResponse::success(['status' => 'processed']);

        return null;
    }
}
