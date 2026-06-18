<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Mail\MailServiceInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/SystemProcessMailQueueAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemProcessMailQueueAction implements ViewActionInterface
{
    public function __construct(private MailServiceInterface $mailService)
    {
    }

    public function execute(array $requestData): void
    {
        $this->mailService->processQueue(10);
        JsonResponse::success(['status' => 'processed']);
    }
}
