<?php

/**
 * Kryptografische Schlüssel und Drittanbieter-Gedöhns
 *
 * Enthält sensible API-Tokens, Salts und Kommunikations-Geheimnisse.
 * Diese Datei darf niemals im öffentlichen Repository landen!
 *
 * Path: config/secrets.php
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    // --- SICHERHEITS-SALTS ---
    'geheimnis'   => 'DEIN_SUPER_GEHEIMES_PASSWORT_HIER', // Sichert die Hash-Tokens der E-Mail-Validierung
    'cron_secret' => 'geheimes_passwort_123',             // Authentifizierungstoken für Web-Cron-Aufrufe

    // --- GOOGLE ANALYTICS 4 (SERVER-SIDE) ---

    /**
     * 'ga4_server_side' => [
     * 'measurement_id' => 'G-XXXXXXXXXX', // Deine normale GA4 Mess-ID
     * 'api_secret'     => 'DEIN_API_GEHEIMNIS_AUS_GA4', // Das generierte API-Geheimnis
     * ],
     *
     * Wie du das API-Geheimnis in Google Analytics erstellst:
     * 1. Öffne dein Google Analytics 4 Dashboard.
     * 2. Gehe unten links auf Verwaltung (Zahnrad) ➔ Datenströme (Data Streams).
     * 3. Klicke auf deinen bestehenden Web-Datenstrom.
     * 4. Scrolle nach unten und klicke unter Zusätzliche Einstellungen auf Measurement Protocol-API-Geheimnisse
     *    (Measurement Protocol API secrets).
     * 5. Klicke auf Erstellen, gib dem Key einen Namen (z. B. "PHP-Server") und kopiere den generierten
     *    Geheimnis-Wert in deine Config-Datei.
     */
    'ga4_server_side' => [
        'measurement_id' => 'G-R0T4M36HCX', // Google Analytics ID (z.B. 'G-R0T4M36HCX')
        'api_secret'     => 'DEIN_API_GEHEIMNIS_AUS_GA4', // Das generierte API-Geheimnis
    ],
];
