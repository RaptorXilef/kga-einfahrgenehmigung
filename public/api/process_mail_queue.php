<?php

/**
 * API: Für E-Mail-Versand.
 *
 * Path: public/api/process_mail_queue.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('process_mail_queue');
