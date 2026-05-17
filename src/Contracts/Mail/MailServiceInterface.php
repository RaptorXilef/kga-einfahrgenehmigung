<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path:      src/Contracts/Mail/MailServiceInterface.php

declare(strict_types=1);

namespace App\Contracts\Mail;

/**
 * Interface für den E-Mail-Versanddienst.
 */
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

    /**
     * Lädt den Verlauf der gesendeten E-Mails.
     * @return array<int, array<string, mixed>>
     */
    public function loadLogs(): array;

    public function saveLogs(array $logs): void;
}
