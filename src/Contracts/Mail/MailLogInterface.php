<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Contracts/Mail/MailLogInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface MailLogInterface
{
    // TODO DOCBLOCK
    public function loadLogs(): array;

    // TODO DOCBLOCK
    public function saveLogs(array $logs, bool $forceSql = false): void;
}
