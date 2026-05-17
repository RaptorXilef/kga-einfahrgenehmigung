<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Entry Point: My Profile
 * Path: public/profile.php
 */

declare(strict_types=1);

use App\Application\UserController;
use App\Contracts\Mail\MailServiceInterface;

// 1. Nutze den zentralen Bootstrapper (garantiert alle Pfade und den Container)
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

// 2. Controller direkt aus dem Container holen
$controller = $container->get(UserController::class);

// 3. Request an den Controller übergeben
$controller->handleProfileRequest($_POST);

// 4. Mail-Queue verarbeiten
try {
    $mailService = $container->get(MailServiceInterface::class);
    if ($mailService instanceof \App\Core\Service\MailQueueService) {
        $mailService->processQueue(10);
    }
} catch (\Throwable $e) {
    // Silent fail für Mails
}
