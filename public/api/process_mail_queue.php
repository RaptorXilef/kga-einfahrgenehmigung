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

use App\Application\Response\JsonResponse;
use App\Contracts\Mail\MailServiceInterface;

$container = require __DIR__ . '/../../src/Bootstrap/app.php';

JsonResponse::enforceCsrfProtection();

$container->get(MailServiceInterface::class)->processQueue(10);

JsonResponse::success(['status' => 'processed']);
