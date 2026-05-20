<?php

// Pfad: config\colors.php

declare(strict_types=1);

/**
 * Antrags-Vorlagen
 */

return [
    // --- TEMPLATES FÜR GENEHMIGUNGEN ---
    'permit_templates' => [
        'std.7' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung 7 Tage',
            'days'   => 7,
            'prices' => ['pkw' => 3.00, 'lkw' => 10.00],
            'public' => true, // Im öffentlichen Formular sichtbar
        ],
        'std.14' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung 14 Tage',
            'days'   => 14,
            'prices' => ['pkw' => 5.00, 'lkw' => 15.00],
            'public' => false, // Nur Admin oder via Gutschein
        ],
        'std.30' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung 1 Monat',
            'days'   => 30,
            'prices' => ['pkw' => 10.00, 'lkw' => 25.00],
            'public' => false,
        ],
        'perm.3' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (1 Quartal)',
            'days'   => 90,
            'prices' => ['pkw' => 20.00, 'lkw' => 50.00],
            'public' => false,
        ],
        'perm.6' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (2 Quartale)',
            'days'   => 180,
            'prices' => ['pkw' => 35.00, 'lkw' => 80.00],
            'public' => false,
        ],
        'perm.9' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (Gesamtjahr)',
            'days'   => 270,
            'prices' => ['pkw' => 60.00, 'lkw' => 150.00],
            'public' => false,
        ],
        'perm.12' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (Gesamtjahr)',
            'days'   => 365,
            'prices' => ['pkw' => 60.00, 'lkw' => 150.00],
            'public' => false,
        ],
        'custom.std' => [
            'type'   => 'standard',
            'label'  => 'Spezialzeitraum (Standard)',
            'days'   => 'custom',
            'prices' => ['pkw' => 0.00, 'lkw' => 0.00], // Manuelle Preisabsprache
            'public' => false,
        ],
        'custom.perm' => [
            'type'   => 'permanent',
            'label'  => 'Spezialzeitraum (Dauereinfahrt)',
            'days'   => 'custom',
            'prices' => ['pkw' => 0.00, 'lkw' => 0.00],
            'public' => false,
        ],
        'std.klause' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung Klause',
            'days'   => 'custom',
            'prices' => ['pkw' => 0.00, 'lkw' => 0.00],
            'public' => false,
        ],
    ],
];
