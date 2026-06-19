<?php

/**
 * Verifizierungs-Einstiegspunkt
 *
 * Path: public/verify.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\VerificationController;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Mail\MailQueueService;

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(VerificationController::class);

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
