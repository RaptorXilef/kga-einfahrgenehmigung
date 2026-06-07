<?php

/**
 * Impressum Konfigurieren
 *
 * Path: config/impressum.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    /** Vorlage / Template */
    'title'    => 'Impressum',
    'verein'   => 'Kleingärtnerverein Mustergarten e.V.',
    'adresse'  => 'Musterstraße 42, 10115 Berlin',
    'vorstand' => [
        'Max Mustermann (1. Vorsitzender)',
        'Erika Mustermann (2. Vorsitzende)',
    ],
    'kontakt' => [
        'telefon' => '+49 (0) 30 1234567',
        'email'   => 'vorstand@deinedomain.de',
    ],
    'register' => [
        'gericht' => 'Amtsgericht Charlottenburg (Berlin)',
        'nummer'  => 'VR 12345 B',
    ],
    'ust_id'                 => null, // Falls vorhanden, z.B. 'DE123456789', sonst null
    'verantwortlich_18_mstv' => [
        'name'    => 'Max Mustermann',
        'adresse' => 'Anschrift wie oben',
    ],
];
