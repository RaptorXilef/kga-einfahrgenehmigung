<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\System\JsonHelperInterface;
use PDO;

/**
 * Factory zur Erstellung der aktiven Storage-Engine.
 *
 * Wertet die Systemkonfiguration aus und initialisiert entweder das
 * relationale MySQL-Backend für Hauptdaten.
 */
final class StorageFactory
{
    /**
     * Instanziiert das korrekte Storage-Backend basierend auf der Konfiguration.
     *
     * @param ConfigInterface $config Die Systemkonfiguration.
     * @param PDO|null $pdo Die aktive Datenbankverbindung (optional).
     *
     * @return StorageInterface Das MySQL-Storage-Objekt.
     */
    public static function create(
        ?PDO $pdo,
        ConfigInterface $config,
        JsonHelperInterface $jsonHelper,
    ): StorageInterface {
        // Wir erzwingen MySqlStorage!
        return new MySqlPermitStorage($pdo, $config, $jsonHelper);
    }
}
