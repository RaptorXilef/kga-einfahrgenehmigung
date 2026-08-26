<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Migration 018: Benennt 'groups' vollständig in 'roles' um (Datenbank, JSON, Ordner).
 */
return static function (\PDO $pdo, ConfigInterface $config): void {
    $storageConfig = $config->get('storage_config');
    $rootPath = \rtrim((string) $config->get('root_path'), '/\\');

    // 1. JSON Dateien umbenennen
    $oldJsonPath = $config->getStoragePath('groups.json');
    $newJsonPath = $config->getStoragePath('roles.json');

    if (\file_exists($oldJsonPath)) {
        \rename($oldJsonPath, $newJsonPath);
    }

    // 2. Bild-Ordner umbenennen
    $oldImgPath = $rootPath . '/public/assets/img/group_images';
    $newImgPath = $rootPath . '/public/assets/img/role_images';

    if (\is_dir($oldImgPath)) {
        \rename($oldImgPath, $newImgPath);
    }

    // 3. MySQL Tabelle umbenennen (falls vorhanden)
    try {
        // Prüfen ob die alte Tabelle existiert
        $result = $pdo->query("SHOW TABLES LIKE 'groups'");
        if ($result && $result->rowCount() > 0) {
            $pdo->exec('RENAME TABLE `groups` TO `roles`');
        }
    } catch (\PDOException $e) {
        \error_log('Migration 018 (MySQL Rename): ' . $e->getMessage());
    }

    // 4. Permissions-Keys in der Rollen-Tabelle anpassen (system.permissions.groups -> roles)
    // 4a. JSON anpassen
    if (\file_exists($newJsonPath)) {
        $rolesData = \file_get_contents($newJsonPath);
        $rolesData = \str_replace('system.permissions.groups.', 'system.permissions.roles.', $rolesData);
        $rolesData = \str_replace('dashboard.migration.groups.', 'dashboard.migration.roles.', $rolesData);
        \file_put_contents($newJsonPath, $rolesData, \LOCK_EX);
    }

    // 4b. MySQL anpassen
    try {
        $pdo->exec("UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'system.permissions.groups.', 'system.permissions.roles.')");
        $pdo->exec("UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'dashboard.migration.groups.', 'dashboard.migration.roles.')");
    } catch (\PDOException $e) {
        // Ignorieren, falls MySQL nicht genutzt wird
    }

    // 5. Admin-Benutzer aktualisieren, falls sie explizite Rechte in JSON/MySQL haben
    $usersJsonPath = $config->getStoragePath('users.json');
    if (\file_exists($usersJsonPath)) {
        $usersData = \file_get_contents($usersJsonPath);
        // Da die Nutzer intern in der PHP Klasse bereits $roleId als Eigenschaft haben,
        // müssen wir in der JSON meist nichts ändern, da es nur Key-Value ist.
    }
};
