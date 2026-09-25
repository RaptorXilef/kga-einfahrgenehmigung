<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Modules\System\Domain\MailLogEntry;

/**
 * Vertrag für das revisionssichere und speicherschonende Protokollieren von System-E-Mails.
 */
interface MailLogInterface
{
    /**
     * Speichert einen einzelnen Log-Eintrag atomar und bereinigt Einträge über dem Limit.
     */
    public function insertLog(MailLogEntry $entry, int $maxEntries = 5000): void;

    /**
     * Sucht gezielt einen einzelnen Log-Eintrag anhand seines Zeitstempels (Y-m-d H:i:s).
     */
    public function findByTimestamp(string $timestamp): ?MailLogEntry;

    /**
     * Gibt den rohen HTML-Inhalt einer Debug-E-Mail sicher zurück (ohne direkten File-Zugriff im Frontend).
     */
    public function getDebugMailContent(string $filename): ?string;
}
