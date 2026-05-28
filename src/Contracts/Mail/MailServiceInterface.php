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
 * Path: src/Contracts/Mail/MailServiceInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface MailServiceInterface
{
    /**
     * @param string               $recipient Die E-Mail-Adresse des Empfängers.
     * @param string               $subject   Betreffzeile der E-Mail.
     * @param string               $template  Pfad zum Template relativ zum Template-Ordner.
     * @param array<string, mixed> $data      Platzhalter- und Payload-Daten für das Template.
     *
     * @return bool|string True bei Erfolg, Fehlermeldung als String bei Fehlern.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string;

    /**
     * Lädt sämtliche aufgezeichneten E-Mail-Versandprotokolle.
     *
     * @return array<int, array<string, mixed>> Liste der Log-Einträge mit Zeitstempel, Empfänger und Status.
     */
    public function loadLogs(): array;

    /**
     * Überschreibt oder persistiert eine Liste von E-Mail-Protokollen.
     *
     * @param array<int, array<string, mixed>> $logs Die zu speichernden Log-Datensätze.
     */
    public function saveLogs(array $logs, bool $forceSql = false): void;
}
