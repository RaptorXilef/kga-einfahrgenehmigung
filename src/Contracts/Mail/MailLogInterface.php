<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Core\Entity\MailLogEntry;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface MailLogInterface
{
    /**
     * @return MailLogEntry[]
     */
    public function loadLogs(): array;

    /**
     * @param MailLogEntry[] $logs
     */
    public function saveLogs(array $logs, bool $forceSql = false): void;

    public function importLogs(array $data, bool $forceSql = false): void;
}
