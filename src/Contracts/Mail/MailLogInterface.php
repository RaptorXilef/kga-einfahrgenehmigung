<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface MailLogInterface
{
    // TODO DOCBLOCK
    public function loadLogs(): array;

    // TODO DOCBLOCK
    public function saveLogs(array $logs, bool $forceSql = false): void;
}
