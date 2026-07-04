<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Ergänzt fehlende Einstellung in storage.json, falls nötig
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    $jsonStoragePath = $config->getStoragePath('settings/storage.json');

    if (\file_exists($jsonStoragePath)) {
        $jsonContent = \file_get_contents($jsonStoragePath);
        $jsonData    = \json_decode($jsonContent, true);

        // Prüfen ob storage_config da ist, aber audit_logs fehlt
        if (\is_array($jsonData) && isset($jsonData['storage_config']) && ! isset($jsonData['storage_config']['audit_logs'])) {

            $inheritedType = $jsonData['storage_config']['permits']['type'] ?? 'json';

            $jsonData['storage_config']['audit_logs'] = [
                'type'  => $inheritedType,
                'table' => 'audit_logs',
                'file'  => 'audit_logs.json',
            ];

            \file_put_contents(
                $jsonStoragePath,
                \json_encode($jsonData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
                \LOCK_EX,
            );
        }
    }
};
