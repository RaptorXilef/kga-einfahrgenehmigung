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

// 2. Konfiguration laden & mergen
$baseSettings = require $appRoot . '/config/config.php';

$localSettings = [];
if (\file_exists($appRoot . '/config/config.local.php')) {
    $localSettings = require $appRoot . '/config/config.local.php';
}

$settings              = \array_replace_recursive($baseSettings, $localSettings);
$settings['root_path'] = $appRoot;

// --- NEU: Erweiterte Wartungsmodus-Logik ---
$currentScript     = \basename($_SERVER['SCRIPT_NAME']);
$isMaintenancePage = $currentScript === 'maintenance.php';

if (! $isMaintenancePage) {
    $adminMaintenance  = ! empty($settings['maintenance_mode_admin']);
    $publicMaintenance = ! empty($settings['maintenance_mode']);

    if ($adminMaintenance) {
        // STUFE 2: Totalsperre (Alles außer die Wartungsseite selbst)
        \header('Location: ' . ($settings['base_url'] ?? '/') . 'maintenance.php');
        exit;
    }

    if ($publicMaintenance) {
        // STUFE 1: Nur Pächter-Seiten sperren
        $allowedAdminScripts = ['admin.php', 'users.php'];

        // Hinweis: API-Anrufe lassen wir durch, da sie oft vom Admin-Panel
        // oder für die Preisberechnung im Hintergrund genutzt werden.
        if (! \in_array($currentScript, $allowedAdminScripts, true) && ! \str_contains($_SERVER['SCRIPT_NAME'], '/api/')) {
            \header('Location: ' . ($settings['base_url'] ?? '/') . 'maintenance.php');
            exit;
        }
    }
}
// --- Ende Wartungsmodus ---

// Wir geben direkt die Container-Instanz zurück
return new Container(new Config($settings));
