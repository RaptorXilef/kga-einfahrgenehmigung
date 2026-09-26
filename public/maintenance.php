<?php

/**
 * Leichtgewichtiger, zu 100 % ausfallsicherer Fallback-Wrapper für die Wartungsseite.
 * Funktioniert auch dann, wenn Konfigurationsdateien, CSS oder PHTML-Templates
 * während eines Datei-Uploads kurzzeitig fehlen oder unvollständig sind.
 *
 * Path: public/maintenance.php
 */

declare(strict_types=1);

if (!\headers_sent()) {
    \http_response_code(503);
    \header('Content-Type: text/html; charset=utf-8');
    \header('Retry-After: 120');
}

$appRootDir = \dirname(__DIR__);

// 1. Fehlertolerantes Laden der Konfiguration
if (!isset($settings) || !\is_array($settings)) {
    $settings = [];
    $configFiles = [
        $appRootDir . '/config/app.default.php',
        $appRootDir . '/config/config.default.php',
        $appRootDir . '/config/app.php',
        $appRootDir . '/config/config.php',
        $appRootDir . '/config/app.local.php',
        $appRootDir . '/config/config.local.php',
    ];

    foreach ($configFiles as $cfgFile) {
        if (!\is_file($cfgFile) || !\is_readable($cfgFile)) {
            continue;
        }

        try {
            $loaded = require $cfgFile;
            if (\is_array($loaded)) {
                $settings = \array_replace_recursive($settings, $loaded);
            }
        } catch (\Throwable) {
            // Beschädigte Config während Upload ignorieren
        }
    }
}

// 2. Fallback-Logik für base_url
if (($settings['base_url'] ?? '') === '') {
    $scriptName = (string) \filter_input(\INPUT_SERVER, 'SCRIPT_NAME');
    $scriptPath = \str_replace('\\', '/', \dirname($scriptName));
    $rootPath = \rtrim($scriptPath, '/public');
    $settings['base_url'] = \rtrim($rootPath, '/') . '/';
}

$vereinsName = (string) ($settings['vereins_name'] ?? 'KGA e.V.');
$displayMessage = (string) ($settings['maintenance_message'] ?? $settings['maintenance']['message'] ?? "Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.\nIn Kürze sind wir wieder für Sie da.");
$maintenanceModeAdmin = (bool) ($settings['maintenance_mode_admin'] ?? $settings['maintenance']['admin'] ?? false);
$baseUrl = (string) $settings['base_url'];
$cspNonce = \defined('CSP_NONCE') ? (string) CSP_NONCE : '';

// Suche Logo
$logoFile = null;
foreach (['webp', 'png', 'jpg'] as $ext) {
    $localPath = __DIR__ . \DIRECTORY_SEPARATOR . 'assets' . \DIRECTORY_SEPARATOR . 'img' . \DIRECTORY_SEPARATOR . 'logo' . \DIRECTORY_SEPARATOR . "kga.$ext";
    if (\is_file($localPath)) {
        $logoFile = "assets/img/logo/kga.$ext";
        break;
    }
}

// 3. Fehlertolerantes Rendering der oberen Menüleiste
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

// 4. Fehlertolerantes Rendering des globalen Footers
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

// 5. Haupt-Wartungstemplate fehlertolerant rendern (mit Zero-Dependency-Fallback)
$maintenanceTemplate = $appRootDir . '/templates/pages/frontend/maintenance.phtml';
$renderedOutput = '';

if (\is_file($maintenanceTemplate) && \is_readable($maintenanceTemplate)) {
    $obLevel = \ob_get_level();
    \ob_start();

    try {
        include $maintenanceTemplate;
        $renderedOutput = (string) \ob_get_clean();
    } catch (\Throwable) {
        while (\ob_get_level() > $obLevel) {
            \ob_end_clean();
        }
        $renderedOutput = '';
    }
}

if (\trim($renderedOutput) !== '') {
    echo $renderedOutput;

    return;
}

// 6. Ultimativer Zero-Dependency-Fallback (keine externen Dateien nötig)
$safeClub = \htmlspecialchars($vereinsName, \ENT_QUOTES, 'UTF-8');
$safeMsg = \nl2br(\htmlspecialchars($displayMessage, \ENT_QUOTES, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Wartungsarbeiten - <?php echo $safeClub; ?></title>
    <style>
    :root {
        color-scheme: light dark;
    }

    body {
        margin: 0;
        padding: 1.5rem;
        font-family: system-ui, -apple-system, sans-serif;
        background: #f8fafc;
        color: #1e293b;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        box-sizing: border-box;
    }

    @media (prefers-color-scheme: dark) {
        body {
            background: #0f172a;
            color: #f1f5f9;
        }

        .card {
            background: #1e293b !important;
            border-color: #334155 !important;
        }

        .muted {
            color: #94a3b8 !important;
        }
    }

    .card {
        max-width: 34rem;
        width: 100%;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-top: 5px solid #f59e0b;
        border-radius: 12px;
        padding: 2.5rem 2rem;
        text-align: center;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    }

    h1 {
        margin: 0.75rem 0;
        font-size: 1.75rem;
    }

    p {
        line-height: 1.6;
        font-size: 1.05rem;
        margin: 0.75rem 0;
    }

    .muted {
        color: #64748b;
        font-size: 0.9rem;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(148, 163, 184, 0.25);
    }

    </style>
</head>

<body>
    <main class="card">
        <div style="font-size: 3rem; line-height: 1;">🛠️</div>
        <h1>Kurze Pause!</h1>
        <p><?php echo $safeMsg; ?></p>
        <p><strong>In Kürze sind wir wieder für Sie da.</strong></p>
        <div class="muted"><?php echo $safeClub; ?> &bull; Vielen Dank für Ihr Verständnis.</div>
    </main>
</body>

</html>
