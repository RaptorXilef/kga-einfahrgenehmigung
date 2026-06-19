<?php

/**
 * Bestätigungsseite für erfolgreiche Antragstellung.
 *
 * Path: public/success.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Actions\SuccessAction;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Mail\MailQueueService;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(SuccessAction::class);
$action->execute($_GET);

// --- E-Mails sofort im Hintergrund abarbeiten ---
// Da in finalize_wire.php die Rechnung in die Queue gelegt wurde,
// triggern wir die Queue hier an, damit die Mail sofort ankommt!
try {
    $mailService = $container->get(MailServiceInterface::class);
    if ($mailService instanceof MailQueueService) {
        $mailService->processQueue(10);
    }
} catch (\Throwable) {
}
