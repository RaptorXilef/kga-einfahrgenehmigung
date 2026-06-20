<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
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
    public function __construct(
        private MailServiceInterface $mailService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        // Polymorphismus: Liskov-Verstoß ist behoben, processQueue ist im Interface
        if (\method_exists($this->mailService, 'processQueue')) {
            $this->mailService->processQueue(10);

            return JsonResponse::success(['status' => 'processed']);
        }

        return JsonResponse::success(['status' => 'skipped_no_queue']);
    }
}
