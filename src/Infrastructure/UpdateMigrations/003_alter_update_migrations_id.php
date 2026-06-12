<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {

    if ($pdo instanceof \PDO) {
        try {
            // 1. Schritt: AUTO_INCREMENT sicher entfernen, indem wir die Spalte als normalen INT überschreiben.
            // (Manche MySQL/MariaDB-Versionen werfen einen Fehler, wenn man die Typ-Änderung und
            // die AI-Entfernung in einem einzigen Schritt erzwingt).
            $pdo->exec('ALTER TABLE `update_migrations` MODIFY COLUMN `id` INT NOT NULL;');

            // 2. Schritt: Den Datentyp gefahrlos auf VARCHAR(50) ändern.
            // Bestehende Zähler-Werte (1, 2, 3...) bleiben als Strings ('1', '2', '3'...) erhalten.
            $pdo->exec('ALTER TABLE `update_migrations` MODIFY COLUMN `id` VARCHAR(50) NOT NULL;');

        } catch (\PDOException $e) {
            // Wir loggen den Fehler leise. Das fängt auch den Fall ab, falls die Tabelle
            // bei einem frischen System direkt über das neue Schema (002) korrekt als VARCHAR angelegt wurde
            // und dieser ALTER TABLE Befehl fehlschlägt.
            \error_log('Migration 003 (MySQL Alter Table update_migrations): ' . $e->getMessage());
        }
    }

};
