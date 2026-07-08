<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {

    // 1. Gruppe-Berechtigungen (JSON) korrigieren
    $groupsFile = $config->get('storage_config')['groups']['file'] ?? 'groups.json';
    $groupsPath = $config->getStoragePath($groupsFile);

    if (\file_exists($groupsPath)) {
        $groups  = \json_decode(\file_get_contents($groupsPath), true);
        $changed = false;
        foreach ($groups as &$group) {
            if (isset($group['permissions']) && \is_array($group['permissions'])) {
                foreach ($group['permissions'] as $k => $perm) {
                    if (\str_starts_with($perm, 'template.')) {
                        // Wandelt 'template.std.7' in 'template.std_7' um
                        $fixedPerm = \preg_replace('/^template\.([a-z]+)\.(\w+)$/', 'template.$1_$2', $perm);
                        if ($fixedPerm !== $perm) {
                            $group['permissions'][$k] = $fixedPerm;
                            $changed                  = true;
                        }
                    }
                }
            }
        }
        unset($group);
        if ($changed) {
            \file_put_contents($groupsPath, \json_encode($groups, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
        }
    }

    // 2. MySQL (falls genutzt) korrigieren
    if ($pdo instanceof \PDO) {
        $storageConfig = $config->get('storage_config', []);

        // Permits anpassen
        $tables = [
            $storageConfig['permits']['table'] ?? 'permits',
            $storageConfig['permits_archive']['table'] ?? 'permits_archive',
            $storageConfig['permits_cancelled']['table'] ?? 'permits_cancelled',
        ];

        foreach ($tables as $table) {
            try {
                $pdo->exec("UPDATE `{$table}` SET `template_key` = REPLACE(`template_key`, '.', '_') WHERE `template_key` LIKE '%.%'");
            } catch (\PDOException $e) {
                \error_log("Migration 017 (MySQL) - TemplateKey in $table: " . $e->getMessage());
            }
        }

        // Gruppen anpassen
        $groupsTable = $storageConfig['groups']['table'] ?? 'groups';

        try {
            // MySQL REPLACE Funktion für die JSON-Strings (std. -> std_)
            $pdo->exec("UPDATE `{$groupsTable}` SET `permissions` = REPLACE(`permissions`, 'template.std.', 'template.std_') WHERE `permissions` LIKE '%template.std.%'");
            $pdo->exec("UPDATE `{$groupsTable}` SET `permissions` = REPLACE(`permissions`, 'template.perm.', 'template.perm_') WHERE `permissions` LIKE '%template.perm.%'");
            $pdo->exec("UPDATE `{$groupsTable}` SET `permissions` = REPLACE(`permissions`, 'template.custom.', 'template.custom_') WHERE `permissions` LIKE '%template.custom.%'");
        } catch (\PDOException $e) {
            \error_log('Migration 017 (MySQL) - Groups: ' . $e->getMessage());
        }
    }
};
