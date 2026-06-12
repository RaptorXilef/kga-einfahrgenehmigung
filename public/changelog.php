<?php

/**
 * Zeigt den Verlauf der Entwicklung aus der CHANGELOG.MD
 *
 * Path: public/changelog.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\AuthService;

try {
    // 1. System hochfahren
    $container = require_once __DIR__ . '/../src/Bootstrap/app.php';
    $config    = $container->get(ConfigInterface::class);
    $auth      = $container->get(AuthService::class);

    // Harte Zugangssperre für unbefugte Nutzer
    if (! $auth->isLoggedIn() || ! $auth->hasPermission('system.update.view')) {
        \header('Location: index.php'); // Zurück zur Startseite werfen
        exit;
    }

    // 2. CHANGELOG.md Datei suchen und auslesen
    $root          = \rtrim((string) $config->get('root_path'), '/\\');
    $changelogPath = $root . '/CHANGELOG.md';

    // Fallback auf Großschreibung
    if (! \file_exists($changelogPath)) {
        $changelogPath = $root . '/CHANGELOG.MD';
    }

    $markdownContent = \file_exists($changelogPath)
        ? \file_get_contents($changelogPath)
        : 'Kein Changelog gefunden.';

    $settings = [
        'vereins_name' => $config->get('vereins_name'),
        'base_url'     => $config->getBaseUrl(),
    ];

    // 3. Daten an das Template übergeben
    \extract([
        'auth'            => $auth,
        'config'          => $config,
        'settings'        => $settings,
        'markdownContent' => $markdownContent,
        'appRoot'         => $root,
    ]);

    // 4. Template laden
    include $root . '/templates/pages/changelog.phtml';

} catch (\Throwable $e) {
    exit('System-Fehler: ' . $e->getMessage());
}
