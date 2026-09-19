<?php

/**
 * Core-Systemsteuerung (Infrastruktur & Umgebung)
 *
 * Diese Datei definiert die grundlegende Laufzeitumgebung des Servers.
 * Änderungen hier sollten nur durch den Systemadministrator vorgenommen werden.
 *
 * Path: config/config.php
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    // --- WARTUNGSMODUS (MAINTENANCE) ---
    // Feingranulare Steuerung für Ausfallzeiten und Updates.
    'maintenance' => [
        // Globale Schalter
        'frontend' => false, // true = Sperrt das gesamte öffentliche Frontend
        'admin' => false,    // true = Sperrt zusätzlich das gesamte Admin-Dashboard

        // Globale Standard-Nachricht für Pächter
        'message' => "Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.\nIn Kürze sind wir wieder für Sie da.",

        // Granulare Steuerung einzelner Seiten (Pfad => true | "Eigener Grund")
        'routes' => [
            // Beispiele:
            // '/checkout' => 'Das Bezahlsystem ist wegen eines Bank-Updates kurzzeitig offline.',
            // '/check'    => true, // Nutzt die globale Nachricht
        ],
    ],

    // --- UMGEBUNGSSTEUERUNG ---
    'test_mode' => false, // true = Sandbox-Modus (PayPal & Mails umgeleitet) | false = Produktion
    'debug_mode' => false, // true = Zeigt PHP-Fehler im Klartext und deaktiviert den Routen-Cache
];
