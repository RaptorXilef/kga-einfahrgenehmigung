<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Migration 019: Benennt 'groups' vollständig in 'roles' um (Datenbank, JSON, Ordner).
 */
return static function (?\PDO $pdo, ConfigInterface $config): void {
    $rootPath = \rtrim((string) $config->get('root_path'), '/\\');

    // 1. JSON Dateiumbenennung mit Merge-Fallback
    $oldJsonPath = $config->getStoragePath('groups.json');
    $newJsonPath = $config->getStoragePath('roles.json');

    if (\file_exists($oldJsonPath)) {
        if (!\file_exists($newJsonPath)) {
            \rename($oldJsonPath, $newJsonPath);
        } else {
            // Bei Dateikollision sicher zusammenführen
            $oldData = \json_decode(\file_get_contents($oldJsonPath) ?: '[]', true) ?: [];
            $newData = \json_decode(\file_get_contents($newJsonPath) ?: '[]', true) ?: [];
            $merged = \array_replace_recursive($oldData, $newData);
            \file_put_contents($newJsonPath, \json_encode($merged, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
            @\unlink($oldJsonPath);
        }
    }

    // 2. Icon Ordner umbenennen
    $oldImgPath = $rootPath . '/public/assets/img/group_images';
    $newImgPath = $rootPath . '/public/assets/img/role_images';
    if (\is_dir($oldImgPath) && !\is_dir($newImgPath)) {
        \rename($oldImgPath, $newImgPath);
    }

    // 3. MySQL Tabellen und Daten migrieren
    if ($pdo !== null) {
        try {
            $hasGroups = $pdo->query("SHOW TABLES LIKE 'groups'")->rowCount() > 0;
            $hasRoles = $pdo->query("SHOW TABLES LIKE 'roles'")->rowCount() > 0;

            if ($hasGroups && !$hasRoles) {
                // Sauberes Rename, falls Ziel nicht existiert
                $pdo->exec('RENAME TABLE `groups` TO `roles`');
            } elseif ($hasGroups && $hasRoles) {
                // Wenn Tabelle bereits existiert (z.B. durch SchemaRegistry & Bootstrapper), Daten sicher umkopieren
                $pdo->exec('INSERT IGNORE INTO `roles` SELECT * FROM `groups`');
                $pdo->exec('DROP TABLE `groups`');
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException('Migration 018 (MySQL Rename): ' . $e->getMessage(), 0, $e);
        }
    }

    // 4. Strings in Berechtigungen umschreiben (Sowohl JSON als auch SQL)
    if (\file_exists($newJsonPath)) {
        $rolesData = \file_get_contents($newJsonPath);
        $rolesData = \str_replace('system.permissions.groups.', 'system.permissions.roles.', $rolesData);
        $rolesData = \str_replace('dashboard.migration.groups.', 'dashboard.migration.roles.', $rolesData);
        \file_put_contents($newJsonPath, $rolesData, \LOCK_EX);
    }

    if ($pdo !== null) {
        try {
            // Prüfen, ob die Tabelle existiert, bevor das Update aufgerufen wird
            $hasRoles = $pdo->query("SHOW TABLES LIKE 'roles'")->rowCount() > 0;
            if ($hasRoles) {
                $pdo->exec("UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'system.permissions.groups.', 'system.permissions.roles.')");
                $pdo->exec("UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'dashboard.migration.groups.', 'dashboard.migration.roles.')");
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException('Migration 018 (MySQL Update Strings): ' . $e->getMessage(), 0, $e);
        }
    }
};
