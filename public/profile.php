<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Entry Point: My Profile
 *
 * Path: <public>profile.php
 */

declare(strict_types=1);

use App\Application\UserController;
use App\Contracts\Mail\MailServiceInterface;

// 1. Nutze den zentralen Bootstrapper (wie in admin.php)
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

// 2. Hol dir den Controller aus dem Container (er hat dann alles: Config, Auth, etc.)
$controller = $container->get(UserController::class);

// 3. Request verarbeiten
$controller->handleProfileRequest($_POST);

// 4. Mail-Queue wie gewohnt anstoßen
try {
    $mailService = $container->get(MailServiceInterface::class);
    if ($mailService instanceof \App\Core\Service\MailQueueService) {
        $mailService->processQueue(5);
    }
} catch (\Throwable $e) {
    // Silent fail für Mails
}
