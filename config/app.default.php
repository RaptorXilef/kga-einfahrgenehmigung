<?php

declare(strict_types=1);

return [
    'base_url' => '', // Leer lassen, System erkennt es selbst!
    'vereins_name' => 'KGA e.V.',
    'prefix' => 'ZM',
    'external_home_url' => 'https://deine-kga-homepage.de',
    'terminkalender_url' => 'https://deine-kga.de/termine',
    'max_plot_number' => 1270,
    'use_long_permit_code' => false,

    'disable_backdoor' => false,     // KGA Sicherheitstoggle
    'disable_superadmin' => false,   // KGA Sicherheitstoggle

    'jahresFarbe' => '#2ecc71',
    'permanent_color' => '#3498db',
    'vorlaeufigFarbe' => '#f1c40f',

    'pagination' => [
        'default_limit' => 25,
        'allowed_limits' => [10, 25, 50, 100, 250],
    ],

    'purposes' => [
        'bau' => 'Baumaßnahmen (genehmigt)',
        'abriss' => 'Abriss',
        'liefer' => 'Lieferung',
        'entsorg' => 'Entsorgung/Abfuhr',
    ],

    'internal_reasons' => [
        'Bargeldzahlung vor Ort',
        'Vorstandsbeschluss',
        'Ersatzgenehmigung',
        'Sommerfest',
    ],
];
