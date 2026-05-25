<?php

/**
 * Admin Einstiegspunkt
 *
 * Path: public/admin.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\AdminController;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\MailQueueService;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(AdminController::class);

// Delegiere alles an die beweisbare Klasse
$controller->handleRequest($_GET, $_POST);

try {
    $mailService = $container->get(MailServiceInterface::class);
    if ($mailService instanceof MailQueueService) {
        // Wir verarbeiten bis zu 10 Mails. Das reicht für Vorstand + Pächter + Dokument
        $mailService->processQueue(10);
    }
} catch (\Throwable) {
    // Fehler beim Mailversand sollen die Seite nicht abstürzen lassen
}
