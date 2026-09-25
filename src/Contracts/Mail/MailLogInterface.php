<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Modules\System\Domain\MailLogEntry;

interface MailLogInterface
{
    /**
     * @return MailLogEntry[]
     */
    public function loadLogs(): array;

    /**
     * Sucht gezielt einen einzelnen Log-Eintrag anhand seines Zeitstempels (Y-m-d H:i:s).
     */
    public function findByTimestamp(string $timestamp): ?MailLogEntry;

    /**
     * @param MailLogEntry[] $logs
     */
    public function saveLogs(array $logs, bool $forceSql = false): void;

    /**
     * Gibt den rohen HTML-Inhalt einer Debug-E-Mail sicher zurück (ohne direkten File-Zugriff im Frontend).
     */
    public function getDebugMailContent(string $filename): ?string;
}
