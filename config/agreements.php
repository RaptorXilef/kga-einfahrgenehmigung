<?php

/**
 * Festlegen, welchen Punkten zugestimmt werden muss, um eine Anfrage zu stellen.
 *
 * Path: config/agreements.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    // Dynamische Checkboxen / Zustimmungen
    'agreements' => [
        'datenschutz' => [
            'label'    => 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu.',
            'link'     => 'https://deinedomain.de/datenschutz/datenschutz.pdf', // Optional, sonst null
            'required' => true,
        ],
        'agb' => [
            'label'    => 'Ich akzeptiere die Platzordnung und die AGB für die Befahrung.',
            'link'     => 'assets/documents/agb.php',
            'required' => true,
        ],
        /*'newsletter' => [
            'label'    => 'Ich möchte künftig über wichtige Änderungen im Verein informiert werden.',
            'link'     => null, // Keine Verlinkung
            'required' => false,
        ],*/
    ],
];
