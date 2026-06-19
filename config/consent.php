<?php

/**
 * Cookie-Banner
 *
 * Path: config/consent.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    'enabled' => true, // Banner komplett ein/ausschalten
    'texts'   => [
        'title'            => 'Datenschutz & Cookies',
        'description'      => 'Wir nutzen Cookies und ähnliche Technologien auf unserer Website. Einige von ihnen sind essenziell für den Betrieb der Seite (z. B. für den Antragsfortschritt), während andere uns helfen, diese Website zu verbessern.',
        'accept_all'       => '🍪 Ich mag Cookies! [Alle akzeptieren] 🍪', // Alle akzeptieren // Ich mag Cookies, die dabei helfen diese Website zu verbessern! [Alle akzeptieren]
        'accept_essential' => '[Nur essenzielle]', // Nur essenzielle // Ich mag keine Cookies! Ich akzeptiere nur die unbedingt nötigen, damit alles funktioniert. [Nur essenzielle]
        'save_selection'   => 'Auswahl speichern',
        'show_details'     => 'Details einblenden',
        'hide_details'     => 'Details ausblenden',
        'link_datenschutz' => 'Datenschutzerklärung',
        'link_impressum'   => 'Impressum',
    ],

    'groups' => [
        'essential' => [
            'id'          => 'essential',
            'title'       => 'Essenziell (Technisch notwendig)',
            'description' => 'Diese Cookies sind für die Kernfunktionen der Website (wie das temporäre Speichern Ihrer Formulardaten und das Session-Management) zwingend erforderlich.',
            'required'    => true,
        ],
        'analytics' => [
            'id'          => 'analytics',
            'title'       => 'Statistiken (Google Analytics)',
            'description' => 'Erfasst anonyme Statistiken über die Nutzung unserer Website, um unser Angebot zu verbessern. Es werden keine personenbezogenen Daten an Google übermittelt. Ihre IP-Adresse wird anonymisiert. Sie helfen uns Sehr, wenn Sie diese Statistik-Cookies akzeptieren.',
            // 'description' => 'Erfasst anonyme Statistiken über die Nutzung unserer Website, um unser Angebot zu verbessern. Es werden keine personenbezogenen Daten an Google übermittelt, ohne dass die IP-Adresse zuvor anonymisiert wurde.',
            'required' => false,
        ],
    ],
];
