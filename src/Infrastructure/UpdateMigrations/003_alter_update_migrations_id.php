<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        try {
            // Nur noch den VARCHAR(50) Typ sicherstellen.
            // Der gefährliche "INT"-Zwischenschritt wurde entfernt, um "0"-Kollisionen zu vermeiden.
            $pdo->exec('ALTER TABLE `update_migrations` MODIFY COLUMN `id` VARCHAR(50) NOT NULL;');
        } catch (\PDOException $e) {
            // Lautlos ignorieren
        }
    }
};
