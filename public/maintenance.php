<?php

/**
 * Anzeige der Wartungsseite
 *
 * Path: public/maintenance.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

// 1. Falls das Script über den Bootstrapper (app.php) läuft, ist $settings schon da.
// Falls nicht (Direktaufruf), laden wir sie hier sicherheitshalber.
if (!isset($settings)) {
    $settings = require __DIR__ . '/../config/config.php';
    if (\file_exists(__DIR__ . '/../config/config.local.php')) {
        $localSettings = require __DIR__ . '/../config/config.local.php';
        $settings = \array_replace_recursive($settings, $localSettings);
    }
}

// 2. Fallback-Logik für base_url, falls sie im Array fehlt (wichtig für Ressourcen)
if (empty($settings['base_url'])) {
    // Fallback auf relativen Pfad, niemals HTTP_HOST vertrauen
    // Und wir ermitteln den Pfad zum Root-Verzeichnis
    $scriptPath = \str_replace('\\', '/', \dirname((string) $_SERVER['SCRIPT_NAME']));
    $rootPath = \rtrim($scriptPath, '/public');
    $settings['base_url'] = \rtrim($rootPath, '/') . '/';
}

$vereinsName = $settings['vereins_name'] ?? 'KGA';

// Suche Logo
$logoFile = null;
foreach (['webp', 'png', 'jpg'] as $ext) {
    // Sicherere Pfadprüfung für Logo
    $localPath = __DIR__ . \DIRECTORY_SEPARATOR . 'assets' . \DIRECTORY_SEPARATOR . 'img' .
        \DIRECTORY_SEPARATOR . 'logo' . \DIRECTORY_SEPARATOR . "kga.$ext";
    if (\file_exists($localPath)) {
        $logoFile = "assets/img/logo/kga.$ext";
        break;
    }
}

$displayMessage = $settings['maintenance_message'] ?? $settings['maintenance']['message'] ?? 'Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.';

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Wartungsarbeiten - <?php echo \htmlspecialchars($vereinsName); ?></title>
    <link rel="stylesheet"
          href="<?php echo $settings['base_url']; ?>assets/css/main.min.css">
</head>

<body class="js-permit-form l-public-body u-justify-center u-bg-light u-padding-block-l">
    <div
         class="c-card c-card--warning o-container o-container--small u-text-center u-padding-block-xl u-shadow-highlight">
        <?php if ($logoFile) { ?>
            <img src="<?php echo $settings['base_url'] . $logoFile; ?>"
                 class="c-logo u-margin-block-end-l"
                 alt="Logo">
        <?php } ?>

        <span class="u-text-4xl u-display-block u-margin-block-end-m">
            <img src="<?php echo $settings['base_url']; ?>assets/img/icons/tools.webp" class="c-icon c-icon--lg" alt="">
        </span>

        <h1 class="u-margin-block-start-none">Kurze Pause!</h1>
        <p class="u-color-muted u-text-lg">
            <?php echo \nl2br(\htmlspecialchars($displayMessage)); ?>
        </p>

        <?php if (!empty($settings['maintenance_mode_admin'])) { ?>
            <div class="c-badge c-badge--danger u-margin-block-start-m">
                Vollständige Systemwartung
            </div>
        <?php } ?>

    </div>
</body>

</html>
