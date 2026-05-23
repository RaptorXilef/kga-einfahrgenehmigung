<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.

// Path: config/secrets.php

declare(strict_types=1);

/**
 * Hier sind alle Softwarespezifischen Geheimnisse / Passwörter zu finden
 */
return [
    // --- SICHERHEIT ---
    // fungiert als "Salt" für die Sicherheit des token in der Zugriffs-URL die per E-Mail versandt wird
    'geheimnis' => 'DEIN_SUPER_GEHEIMES_PASSWORT_HIER',

    // TODO API KEY später nachrüsten
    // API-Secret zur Absicherung aller öffentlichen Endpunkte
    // Nur Anfragen, die dieses Secret im Header mitsenden, dürfen die APIs nutzen.
    // 'api_secret' => 'Raptor_API_Secret_2026_xyz', // Aktuell ungenutzt, da CRFS-Token verwendet wird!
];
