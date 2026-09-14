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

<body class="l-public-body u-justify-center u-bg-light u-padding-block-l">
    <div
         class="c-card c-card--warning o-container o-container--small u-text-center u-padding-block-xl u-shadow-highlight">
        <?php if ($logoFile) { ?>
            <img src="<?php echo $settings['base_url'] . $logoFile; ?>"
                 class="c-logo u-margin-block-end-l"
                 alt="Logo">
        <?php } ?>

        <span class="u-text-4xl u-display-block u-margin-block-end-m">
            <img src="<?php echo $settings['base_url']; ?>assets/img/icons/nav-tools.webp" class="c-icon c-icon--lg" alt="">
        </span>

        <h1 class="u-margin-block-start-none">Kurze Pause!</h1>
        <p class="u-color-muted u-text-lg">
            Wir aktualisieren gerade das System für die <br>
            <strong class="u-color-dark"><?php echo \htmlspecialchars($vereinsName); ?></strong>, <br>
            um Ihnen den bestmöglichen Service zu bieten.
        </p>

        <?php if (!empty($settings['maintenance_mode_admin'])) { ?>
            <div class="c-badge c-badge--danger u-margin-block-start-m">
                Vollständige Systemwartung
            </div>
        <?php } ?>

        <p class="u-font-bold u-color-dark u-margin-block-start-l">
            In Kürze sind wir wieder für Sie da.
        </p>
        <div class="u-border-block-start u-padding-block-start-m u-margin-block-start-l u-color-muted u-text-sm">
            Vielen Dank für Ihr Verständnis.
        </div>
    </div>
</body>

</html>
