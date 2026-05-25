<?php

/**
 * API: Für E-Mail-Versand.
 *
 * Path: public/api/process_mail_queue.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Contracts\Mail\MailServiceInterface;

$container = require __DIR__ . '/../../src/Bootstrap/app.php';

// --- CSRF SECURITY GATEKEEPER ---
$providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken  = $_SESSION['csrf_token'] ?? '';

if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
    \http_response_code(401);
    echo \json_encode(['success' => false, 'error' => 'Unauthorized: Invalid Security Token']);
    exit;
}
// --------------------------------

$container->get(MailServiceInterface::class)->processQueue(10);
echo \json_encode(['status' => 'processed']);
