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

use App\Application\CheckoutController;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\MailQueueService;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(CheckoutController::class);
$controller->handleRequest($_GET);

// E-Mails im Hintergrund abarbeiten
try {
    $mailService = $container->get(MailServiceInterface::class);
    if ($mailService instanceof MailQueueService) {
        $mailService->processQueue(10);
    }
} catch (\Throwable) {
}
