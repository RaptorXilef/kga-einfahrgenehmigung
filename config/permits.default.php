<?php

declare(strict_types=1);

return [
    'permit_templates' => [
        'std_7' => [
            'type' => 'standard',
            'label' => 'Ausnahmegenehmigung 7 Tage',
            'days' => 7,
            'prices' => ['pkw' => 3, 'lkw' => 10, 'sharing' => 3],
            'public' => true,
        ],
        'std_14' => [
            'type' => 'standard',
            'label' => 'Ausnahmegenehmigung 14 Tage',
            'days' => 14,
            'prices' => ['pkw' => 5, 'lkw' => 15, 'sharing' => 5],
            'public' => false,
        ],
        'std_30' => [
            'type' => 'standard',
            'label' => 'Ausnahmegenehmigung 1 Monat',
            'days' => 30,
            'prices' => ['pkw' => 10, 'lkw' => 25, 'sharing' => 10],
            'public' => false,
        ],
        'perm_3' => [
            'type' => 'permanent',
            'label' => 'Dauereinfahrgenehmigung (1 Quartal)',
            'days' => 90,
            'prices' => ['pkw' => 20, 'lkw' => 50, 'sharing' => 20],
            'public' => false,
        ],
        'perm_6' => [
            'type' => 'permanent',
            'label' => 'Dauereinfahrgenehmigung (2 Quartale)',
            'days' => 180,
            'prices' => ['pkw' => 35, 'lkw' => 80, 'sharing' => 35],
            'public' => false,
        ],
        'perm_9' => [
            'type' => 'permanent',
            'label' => 'Dauereinfahrgenehmigung (Gesamtjahr)',
            'days' => 270,
            'prices' => ['pkw' => 60, 'lkw' => 150, 'sharing' => 60],
            'public' => false,
        ],
        'perm_12' => [
            'type' => 'permanent',
            'label' => 'Dauereinfahrgenehmigung (Gesamtjahr)',
            'days' => 365,
            'prices' => ['pkw' => 60, 'lkw' => 150, 'sharing' => 60],
            'public' => false,
        ],
        'custom_std' => [
            'type' => 'standard',
            'label' => 'Spezialzeitraum (Standard)',
            'days' => 'custom',
            'prices' => ['pkw' => 0, 'lkw' => 0, 'sharing' => 0],
            'public' => false,
        ],
        'custom_perm' => [
            'type' => 'permanent',
            'label' => 'Spezialzeitraum (Dauereinfahrt)',
            'days' => 'custom',
            'prices' => ['pkw' => 0, 'lkw' => 0, 'sharing' => 0],
            'public' => false,
        ],
        'std_klause' => [
            'type' => 'standard',
            'label' => 'Ausnahmegenehmigung Klause',
            'days' => 'custom',
            'prices' => ['pkw' => 0, 'lkw' => 0, 'sharing' => 0],
            'public' => false,
        ],
    ],

    'default_opening_hours' => [
        'mon' => [['07:00', '13:00'], ['15:00', '20:00']],
        'tue' => [['07:00', '13:00'], ['15:00', '20:00']],
        'wed' => [['07:00', '13:00'], ['15:00', '20:00']],
        'thu' => [['07:00', '13:00'], ['15:00', '20:00']],
        'fri' => [['07:00', '13:00'], ['15:00', '20:00']],
        'sat' => [['07:00', '13:00'], ['15:00', '20:00']],
        'sun' => [],
    ],

    'seasons' => [
        [
            'start' => '10-01',
            'end' => '03-31',
            'opening_hours' => [
                'mon' => [['07:00', '20:00']],
                'tue' => [['07:00', '20:00']],
                'wed' => [['07:00', '20:00']],
                'thu' => [['07:00', '20:00']],
                'fri' => [['07:00', '20:00']],
                'sat' => [['07:00', '20:00']],
                'sun' => [],
            ],
        ],
    ],

    'use_auto_holidays' => true,
    'holiday_check' => 'Berlin',
    'custom_holidays' => ['2026-12-24'],
    'holiday_service_use_full_list' => false,

    'vehicle_types' => [
        'pkw' => [
            'label' => 'Privat PKW',
            'icon' => 'assets/img/icons/icon-automobile.webp',
            'show_company' => false,
            'active' => true,
        ],
        'lkw' => [
            'label' => 'Lieferant / Firma / LKW',
            'icon' => 'assets/img/icons/icon-delivery-truck.webp',
            'show_company' => true,
            'active' => true,
        ],
        'sharing' => [
            'label' => 'Privat (Leihfahrzeug/Car-Sharing)',
            'icon' => 'assets/img/icons/icon-carsharing.webp',
            'show_company' => true,
            'active' => true,
        ],
        'entsorg' => [
            'label' => 'Abwasser / Entsorgung',
            'icon' => 'assets/img/icons/icon-biohazard.webp',
            'show_company' => true,
            'active' => false,
        ],
    ],
];
