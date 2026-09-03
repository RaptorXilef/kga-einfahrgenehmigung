<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

/**
 * Interface für den E-Mail-Versanddienst und die Protokollierung.
 *
 * Erzwingt die standardisierte Verarbeitung von Template-basierten E-Mails
 * sowie den Lese- und Schreibzugriff auf die Versandprotokolle (Logs).
 * Kontext: Kommunikationsschnittstelle für Systembenachrichtigungen (z.B. Queue oder SMTP-Direktversand).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface MailServiceInterface
{
    /**
     * Reiht eine E-Mail in die Warteschlange ein oder versendet sie direkt.
     *
     * @param string $recipient Die E-Mail-Adresse des Empfängers.
     * @param string $subject Betreffzeile der E-Mail.
     * @param string $template Pfad zum Template relativ zum Template-Ordner.
     * @param array<string, mixed> $data Platzhalter- und Payload-Daten für das Template.
     * @param string|null $replyTo Optionale Antwortadresse.
     * @param int $priority Wichtigkeit (100 = Hoch/Login, 50 = Normal, 10 = Niedrig/Erinnerung).
     *
     * @return bool|string True bei Erfolg, Fehlermeldung als String bei Fehlern.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data, ?string $replyTo = null, int $priority = 50): bool|string;

    // TODO DOCBLOCK
    public function processQueue(int $limit = 5): int;
}
