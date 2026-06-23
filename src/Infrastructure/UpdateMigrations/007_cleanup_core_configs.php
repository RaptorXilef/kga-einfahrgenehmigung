<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Nicht mehr benötigte permission.php und sql_schema.php aus configs/... löschen
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    $appRoot     = \rtrim((string) $config->get('root_path'), '/\\');
    $settingsDir = $appRoot . '/storage/settings';

    // 1. Sicherheits-Check: Lief die JSON-Migration (006) bereits erfolgreich?
    $jsonFiles = (array) \glob($settingsDir . '/*.json');
    if (\count($jsonFiles) === 0) {
        return; // Keine JSONs da = System wurde noch nicht migriert. Abbruch!
    }

    // 2. Zu löschende veraltete Core-Configs, da sie nun als Klassen in src/ leben
    $filesToDelete = [
        $appRoot . '/config/permissions.php',
        $appRoot . '/config/sql_schema.php',
    ];

    foreach ($filesToDelete as $file) {
        if (\file_exists($file) && \is_file($file)) {
            @\unlink($file);
        }
    }
};
