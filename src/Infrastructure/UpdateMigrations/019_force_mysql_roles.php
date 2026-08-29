<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Migration 019: Erzwingt den MySQL-Umzug von 'groups' zu 'roles'.
 * Kopiert Daten, falls 'roles' vom System bereits leer erstellt wurde.
 */
return static function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo === null) {
        return; // Kein MySQL konfiguriert
    }

    // Zuverlässiger Check, ob die Tabellen existieren (rowCount ist bei SELECT oft unzuverlässig)
    $hasGroups = (bool) $pdo->query("SHOW TABLES LIKE 'groups'")->fetch();
    $hasRoles = (bool) $pdo->query("SHOW TABLES LIKE 'roles'")->fetch();

    if ($hasGroups && !$hasRoles) {
        // Fall 1: Normale Umbenennung
        $pdo->exec('RENAME TABLE `groups` TO `roles`');
    } elseif ($hasGroups && $hasRoles) {
        // Fall 2: 'roles' wurde schon leer vom System generiert. Wir kopieren die Daten!
        $pdo->exec('INSERT IGNORE INTO `roles` SELECT * FROM `groups`');
        $pdo->exec('DROP TABLE `groups`'); // Alte Tabelle danach sauber löschen
    }

    // Strings der Permissions in der Datenbank zwingend anpassen
    $hasRolesNow = (bool) $pdo->query("SHOW TABLES LIKE 'roles'")->fetch();
    if ($hasRolesNow) {
        $pdo->exec("UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'system.permissions.groups.', 'system.permissions.roles.')");
        $pdo->exec("UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'dashboard.migration.groups.', 'dashboard.migration.roles.')");
    }
};
