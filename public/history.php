<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: public/history.php

declare(strict_types=1);

use App\Application\HistoryController;

/**
 * Pächter-Verlauf Einstiegspunkt
 * Ermöglicht die Einsicht aller Genehmigungen via Magic Link.
 */

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(HistoryController::class);

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
