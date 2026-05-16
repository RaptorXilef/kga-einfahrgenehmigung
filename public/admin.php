<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Admin Einstiegspunkt v0.9.5
 *
 * Path:      public/admin.php
 */

declare(strict_types=1);

use App\Application\AdminController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(AdminController::class);

// Delegiere alles an die beweisbare Klasse
$controller->handleRequest($_GET, $_POST);

try {
    $mailService = $container->get(\App\Contracts\Mail\MailServiceInterface::class);
    if ($mailService instanceof \App\Core\Service\MailQueueService) {
        // Wir verarbeiten bis zu 10 Mails. Das reicht für Vorstand + Pächter + Dokument
        $mailService->processQueue(10);
    }
} catch (\Throwable $e) {
    // Fehler beim Mailversand sollen die Seite nicht abstürzen lassen
}
