<?php

/**
 * Checkout-Einstiegspunkt
 *
 * Path: public/checkout.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
declare(strict_types=1);

use App\Application\Actions\CheckoutAction;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Mail\MailQueueService;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$action = $container->get(CheckoutAction::class);
$action->execute($_GET);

// E-Mails im Hintergrund abarbeiten
try {
    $mailService = $container->get(MailServiceInterface::class);
    if ($mailService instanceof MailQueueService) {
        $mailService->processQueue(10);
    }
} catch (\Throwable) {
}
