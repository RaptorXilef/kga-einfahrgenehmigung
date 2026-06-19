<?php

/**
 * Allgemeine Einstellungen zur Anzeige und Nutzung
 *
 * Path: config/settings.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    // Paginierungs-Einstellungen
    // Legt fest, wieviele Einträge pro Seite im Admin-Dashboard angezeigt werden
    'pagination' => [
        'default_limit'  => 25,
        'allowed_limits' => [10, 25, 50, 100, 250],
    ],
];
