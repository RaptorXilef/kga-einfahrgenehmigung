<?php

/**
 * Leichtgewichtiger Fallback-Wrapper für die Anzeige der Wartungsseite (z.B. bei Direktaufruf).
 * Isoliert von der Datenbank, falls das System komplett offline ist.
 *
 * Path: public/maintenance.php
 */

declare(strict_types=1);

$appRootDir = \dirname(__DIR__);

// 1. Falls das Script über den Bootstrapper (app.php) läuft, ist $settings schon da.
// Falls nicht (Direktaufruf), laden wir sie hier sicherheitshalber.
if (!isset($settings)) {
    $settings = require $appRootDir . '/config/config.default.php';
    if (\file_exists($appRootDir . '/config/config.php')) {
        $customSettings = require $appRootDir . '/config/config.php';
        $settings = \array_replace_recursive($settings, $customSettings);
    }
    if (\file_exists($appRootDir . '/config/config.local.php')) {
        $localSettings = require $appRootDir . '/config/config.local.php';
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
$cspNonce = \defined('CSP_NONCE') ? (string) CSP_NONCE : '';

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

// 3. Fehlertolerantes Rendering der oberen Menüleiste (darf bei fehlender Datei während Updates niemals abstürzen)
$headerNavHtml = '';
$navPath = $appRootDir . '/templates/partials/frontend/public_header_nav.phtml';
if (\is_file($navPath) && \is_readable($navPath)) {
    $obLevel = \ob_get_level();
    \ob_start();

    try {
        $asset = new readonly class($baseUrl) {
            public function __construct(private string $baseUrl)
            {
            }

            public function url(string $assetPath): string
            {
                return \rtrim($this->baseUrl, '/') . '/' . \ltrim($assetPath, '/');
            }
        };
        $navActiveIndexClass = '';
        $navActiveHistoryClass = '';
        $navActiveCheckClass = '';

        include $navPath;
        $headerNavHtml = (string) \ob_get_clean();
    } catch (\Throwable) {
        while (\ob_get_level() > $obLevel) {
            \ob_end_clean();
        }
        $headerNavHtml = '';
    }
}

// 4. Fehlertolerantes Rendering des globalen Footers (darf bei fehlender Datei während Updates niemals abstürzen)
$footerHtml = '';
$footerPath = $appRootDir . '/templates/partials/frontend/footer.phtml';
$consentPath = $appRootDir . '/templates/partials/frontend/consent_banner.phtml';
if (\is_file($footerPath) && \is_readable($footerPath) && \is_file($consentPath) && \is_readable($consentPath)) {
    $obLevel = \ob_get_level();
    \ob_start();

    try {
        $appRoot = $appRootDir;
        $appVersion = 'v0.0.0';
        $pkgPath = $appRootDir . '/package.json';
        if (\is_file($pkgPath) && \is_readable($pkgPath)) {
            $rawPkg = \file_get_contents($pkgPath);
            if (\is_string($rawPkg)) {
                $decodedPkg = \json_decode($rawPkg, true);
                if (\is_array($decodedPkg) && isset($decodedPkg['version'])) {
                    $appVersion = 'v' . $decodedPkg['version'];
                }
            }
        }

        $currentYear = (int) \date('Y');
        $startYear = 2026;
        $footerYearDisplay = $currentYear > $startYear ? "{$startYear} - {$currentYear}" : (string) $startYear;
        $footerSoftwareName = 'KGA-Einfahrts-Manager';
        $footerIssuesUrl = 'https://github.com/RaptorXilef/kga-einfahrgenehmigung/issues';
        $footerImpressumUrl = $baseUrl . 'impressum';
        $footerDatenschutzUrl = $baseUrl . 'datenschutz';
        $debugMetrics = null;
        $canAccessAdmin = false;
        $consentEnabled = false;
        $consentConfigJson = '{}';
        $consentTitle = '';
        $consentDescription = '';
        $consentLinkDatenschutz = 'Datenschutzerklärung';
        $consentLinkImpressum = 'Impressum';
        $consentAcceptAll = '';
        $consentAcceptEssential = '';
        $consentShowDetails = '';
        $consentSaveSelection = '';
        $consentGroups = [];

        include $footerPath;
        $footerHtml = (string) \ob_get_clean();
    } catch (\Throwable) {
        while (\ob_get_level() > $obLevel) {
            \ob_end_clean();
        }
        $footerHtml = '';
    }
}

// Binden wir das saubere PHTML-Template ein
require $appRootDir . '/templates/pages/frontend/maintenance.phtml';
