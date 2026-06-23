<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Konvertiert das alte PHP Config Format in das neue JSON Format
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    $appRoot     = \rtrim((string) $config->get('root_path'), '/\\');
    $settingsDir = $appRoot . '/storage/settings';

    if (! \is_dir($settingsDir)) {
        @\mkdir($settingsDir, 0o755, true);
    }

    // Die Legacy-Dateien, die in das UI-JSON-Format migriert werden sollen
    $legacyFilesToMigrate = [
        'vehicles'     => 'vehicles.php',
        'templates'    => 'templates.php',
        'times'        => 'times.php',
        'purposes'     => 'purposes.php',
        'reasons'      => 'reasons.php',
        'consent'      => 'consent.php',
        'agreements'   => 'agreements.php',
        'datenschutz'  => 'datenschutz.php',
        'impressum'    => 'impressum.php',
        'organization' => 'organization.php',
        'colors'       => 'colors.php',
        'payment'      => 'payment.php',
        'email'        => 'email.php',
        'settings'     => 'settings.php',
    ];

    foreach ($legacyFilesToMigrate as $key => $filename) {
        $legacyPath = $appRoot . '/config/' . $filename;
        $jsonPath   = $settingsDir . '/' . $key . '.json';

        // Nur migrieren, wenn die alte PHP existiert und die neue JSON noch nicht
        if (\file_exists($legacyPath) && ! \file_exists($jsonPath)) {
            $data = require $legacyPath;
            if (\is_array($data)) {
                // Erzeuge JSONC Format (Mit Meta-Kommentar oben)
                // FIX: Sicheres Hinzufügen des _meta Keys am Anfang des Arrays
                $jsonData = ['_meta' => 'AUTO-GENERATED JSON CONFIG FROM LEGACY PHP FILE'] + $data;
                \file_put_contents($jsonPath, \json_encode($jsonData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);

                // Lösche die alte .php Datei (sodass sie nicht mehr geladen werden kann)
                @\unlink($legacyPath);
            }
        }
    }
};
