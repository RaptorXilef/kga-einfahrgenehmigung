<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Interface für den E-Mail-Versanddienst.
 *
 * @file      src/Contracts/Mail/MailServiceInterface.php
 *
 * @since     0.1.0
 * - feat(mail): Definition der Schnittstelle für Template-basierten Mailversand.
 */

declare(strict_types=1);

namespace App\Contracts\Mail;

interface MailServiceInterface
{
    /**
     * Sendet eine E-Mail basierend auf einem Template.
     *
     * @param string               $recipient Empfänger-Adresse.
     * @param string               $subject   Betreffzeile.
     * @param string               $template  Pfad zum Template relativ zum Template-Ordner.
     * @param array<string, mixed> $data      Daten für die Platzhalter.
     *
     * @return bool|string True bei Erfolg, Fehlermeldung als String bei Fehlern.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string;
}
