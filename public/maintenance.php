<?php

/**
 * Leichtgewichtiger Fallback-Wrapper für die Anzeige der Wartungsseite (z.B. bei Direktaufruf).
 * Isoliert von der Datenbank, falls das System komplett offline ist.
 *
 * Path: public/maintenance.php
 */

declare(strict_types=1);

// 1. Falls das Script über den Bootstrapper (app.php) läuft, ist $settings schon da.
// Falls nicht (Direktaufruf), laden wir sie hier sicherheitshalber.
if (!isset($settings)) {
    $settings = require __DIR__ . '/../config/config.default.php';
    if (\file_exists(__DIR__ . '/../config/config.php')) {
        $customSettings = require __DIR__ . '/../config/config.php';
        $settings = \array_replace_recursive($settings, $customSettings);
    }
    if (\file_exists(__DIR__ . '/../config/config.local.php')) {
        $localSettings = require __DIR__ . '/../config/config.local.php';
        $settings = \array_replace_recursive($settings, $localSettings);
    }
}

// 2. Fallback-Logik für base_url, falls sie im Array fehlt (wichtig für Ressourcen)
if (($settings['base_url'] ?? '') === '') {
    // Fallback auf relativen Pfad, niemals HTTP_HOST vertrauen
    // Und wir ermitteln den Pfad zum Root-Verzeichnis
    $scriptName = (string) \filter_input(\INPUT_SERVER, 'SCRIPT_NAME');
    $scriptPath = \str_replace('\\', '/', \dirname($scriptName));
    $rootPath = \rtrim($scriptPath, '/public');
    $settings['base_url'] = \rtrim($rootPath, '/') . '/';
}

$vereinsName = (string) ($settings['vereins_name'] ?? 'KGA');
$displayMessage = (string) ($settings['maintenance_message'] ?? $settings['maintenance']['message'] ?? 'Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.');
$maintenanceModeAdmin = (bool) ($settings['maintenance_mode_admin'] ?? false);
$baseUrl = (string) $settings['base_url'];

// Suche Logo
$logoFile = null;
foreach (['webp', 'png', 'jpg'] as $ext) {
    // Sicherere Pfadprüfung für Logo
    $localPath = __DIR__ . \DIRECTORY_SEPARATOR . 'assets' . \DIRECTORY_SEPARATOR . 'img' . \DIRECTORY_SEPARATOR . 'logo' . \DIRECTORY_SEPARATOR . "kga.$ext";
    if (\file_exists($localPath)) {
        $logoFile = "assets/img/logo/kga.$ext";
        break;
    }
}

// Binden wir das saubere PHTML-Template ein
require __DIR__ . '/../templates/pages/frontend/maintenance.phtml';
