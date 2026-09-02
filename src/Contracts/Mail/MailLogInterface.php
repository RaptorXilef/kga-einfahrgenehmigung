<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Core\Entity\MailLogEntry;

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
}
