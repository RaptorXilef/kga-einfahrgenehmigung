<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Zentraler Bootstrapper v0.21.0
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

// 3. Konfigurationen laden und mergen
$baseSettings = require $appRoot . '/config/config.php';

$localSettings = [];
if (\file_exists($appRoot . '/config/config.local.php')) {
    $localSettings = require $appRoot . '/config/config.local.php';
}

$settings              = \array_replace_recursive($baseSettings, $localSettings);
$settings['root_path'] = $appRoot;

// Wir geben direkt die Container-Instanz zurück
return new Container(new Config($settings));
