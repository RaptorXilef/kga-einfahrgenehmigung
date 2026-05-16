<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * API: Für E-Mail-Versand.
 *
 * Path: public/api/process_mail_queue.php
 */

declare(strict_types=1);

$container = require __DIR__ . '/../../src/Bootstrap/app.php';

$container->get(\App\Contracts\Mail\MailServiceInterface::class)->processQueue(10);
echo \json_encode(['status' => 'processed']);
