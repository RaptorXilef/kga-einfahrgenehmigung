<?php

/**
 * Allgemeine Einstellungen zur Anzeige und Nutzung
 *
 * Path: config/settings.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
