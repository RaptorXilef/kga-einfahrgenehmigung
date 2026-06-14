<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;

/**
 * Factory zur Erstellung der aktiven Storage-Engine.
 *
 * Wertet die Systemkonfiguration aus und initialisiert entweder das
 * relationale MySQL-Backend oder das dateibasierte JSON-Backend für Hauptdaten.
 *
 * Path: src/Infrastructure/Storage/StorageFactory.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
    public static function create(ConfigInterface $config, ?\PDO $pdo): StorageInterface
    {
        $mapping = $config->get('storage_config')['permits'] ?? ['type' => 'json'];

        if ($mapping['type'] === 'mysql') {
            if (! $pdo) {
                throw new \RuntimeException('Datenbank benötigt, aber MySQL-Server ist offline.');
            }

            return new MySqlStorage($pdo);
        }

        $fileName = $mapping['file'] ?? 'permits_active.json';
        $path     = $config->getStoragePath($fileName);

        return new JsonStorage($path);
    }
}
