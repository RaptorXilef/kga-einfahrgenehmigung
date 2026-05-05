<?php
/**
 * Path: public/maintenance.php
 */
$settings    = require __DIR__ . '/../config/config.php';
$vereinsName = $settings['vereins_name'] ?? 'KGA';

// Suche Logo (gleiche Logik wie im Formular)
$logoFile = null;
foreach (['webp', 'png', 'jpg'] as $ext) {
    if (\file_exists("assets/img/kga_logo.$ext")) {
        $logoFile = "assets/img/kga_logo.$ext";

        break;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartungsarbeiten - <?php echo \htmlspecialchars($vereinsName); ?></title>
    <link rel="stylesheet" href="assets/css/main.min.css">
    <style>
        body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: sans-serif; }
        .c-maintenance-card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 500px; border-top: 5px solid #f59e0b; }
        .c-icon-large { font-size: 50px; margin-bottom: 20px; display: block; }
    </style>
</head>
<body>
    <div class="c-maintenance-card">
        <?php if ($logoFile) { ?>
            <img src="<?php echo $logoFile; ?>" style="max-width: 200px; margin-bottom: 20px;" alt="Logo">
        <?php } ?>

        <span class="c-icon-large"><img src="assets/img/icons/nav-tools.webp" class="c-icon" alt="" style="width: 1.5em; height: 1.5em;"><!-- 🛠️ --></span>
        <h1>Kurze Pause!</h1>
        <p style="color: #64748b; line-height: 1.6;">
            Wir aktualisieren gerade das System für die <strong><?php echo \htmlspecialchars($vereinsName); ?></strong>,
            um Ihnen den bestmöglichen Service zu bieten.
        </p>
        <p style="margin-top: 20px; font-weight: bold; color: #1e293b;">
            In Kürze sind wir wieder für Sie da.
        </p>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 0.85rem; color: #94a3b8;">
            Vielen Dank für Ihr Verständnis.
        </div>
    </div>
</body>
</html>
