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
    // Um einen Bereich zu aktivieren /* und */ entfernen
    'agreements' => [
        /*'datenschutz' => [
            // Das Wort in den eckigen Klammern wird automatisch verlinkt:
            'label'    => 'Ich willige in die Datenverarbeitung gemäß [Datenschutzerklärung] ein.',
            'link'     => 'https://deinedomain.de/datenschutz/datenschutz.pdf',
            'required' => true,
        ],*/
        /*'ordnung' => [
            // Hier wird die gesamte Phrase in den Klammern zum Link:
            'label'    => 'Ich habe [Abschnitt VI der Vereinsordnung] zur Kenntnis genommen.',
            'link'     => 'assets/documents/Ordnung_KGA.pdf',
            'required' => true,
        ],*/
        /*'newsletter' => [
            // Falls mal kein Link existiert, wird der Text trotz Klammern ganz normal dargestellt
            'label'    => 'Ich möchte den [Newsletter] abonnieren.',
            'link'     => null,
            'required' => false,
        ]*/
    ],
];
