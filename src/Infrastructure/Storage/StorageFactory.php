<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\System\JsonHelperInterface;

/**
 * Factory zur Erstellung der aktiven Storage-Engine.
 *
 * Wertet die Systemkonfiguration aus und initialisiert entweder das
 * relationale MySQL-Backend oder das dateibasierte JSON-Backend für Hauptdaten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class StorageFactory
{
    /**
     * Instanziiert das korrekte Storage-Backend basierend auf der Konfiguration.
     *
     * @param ConfigInterface $config Die Systemkonfiguration.
     * @param \PDO|null       $pdo    Die aktive Datenbankverbindung (optional).
     *
     * @return StorageInterface Das JSON- oder MySQL-Storage-Objekt.
     */
    public static function create(
        ?\PDO $pdo,
        ConfigInterface $config,
        JsonHelperInterface $jsonHelper,
    ): StorageInterface {
        $mapping = $config->get('storage_config')['permits'] ?? ['type' => 'json'];

        if ($mapping['type'] === 'mysql') {
            if (! $pdo) {
                throw new \RuntimeException('Datenbank benötigt, aber MySQL-Server ist offline.');
            }

            return new MySqlStorage($pdo, $jsonHelper);
        }
        $fileName = $mapping['file'] ?? 'permits_active.json';
        $path     = $config->getStoragePath($fileName);

        return new JsonStorage($path, $jsonHelper);
    }
}
