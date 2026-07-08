<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    // 1. MySQL Migration (falls genutzt)
    if ($pdo instanceof \PDO) {
        $storageConfig = $config->get('storage_config', []);

        $tables = [
            $storageConfig['permits']['table'] ?? 'permits',
            $storageConfig['permits_archive']['table'] ?? 'permits_archive',
            $storageConfig['permits_cancelled']['table'] ?? 'permits_cancelled',
        ];

        foreach ($tables as $table) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `parzelle` INT NOT NULL DEFAULT 0");
            } catch (\PDOException $e) {
                \error_log("Migration 016 (MySQL) - Parzelle in $table: " . $e->getMessage());
            }
        }

        $vouchersArchiveTable = $storageConfig['vouchers_archive']['table'] ?? 'vouchers_archive';

        try {
            $pdo->exec("ALTER TABLE `{$vouchersArchiveTable}` MODIFY COLUMN `user_plot` INT NULL DEFAULT NULL");
        } catch (\PDOException $e) {
            \error_log('Migration 016 (MySQL) - Vouchers Archive: ' . $e->getMessage());
        }
    }

    // 2. JSON Migration (falls genutzt)
    $jsonKeys = ['permits', 'permits_archive', 'permits_cancelled'];
    foreach ($jsonKeys as $key) {
        $file = $config->get('storage_config')[$key]['file'] ?? null;
        if ($file) {
            $path = $config->getStoragePath($file);
            if (\file_exists($path)) {
                $data    = \json_decode(\file_get_contents($path), true);
                $changed = false;
                foreach ($data as &$row) {
                    if (isset($row['parzelle']) && ! \is_int($row['parzelle'])) {
                        // "0036" wird zu 36
                        $row['parzelle'] = (int) $row['parzelle'];
                        $changed         = true;
                    }
                }
                unset($row);
                if ($changed) {
                    \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
                }
            }
        }
    }

    // Vouchers Archive JSON
    $vArcFile = $config->get('storage_config')['vouchers_archive']['file'] ?? null;
    if ($vArcFile) {
        $path = $config->getStoragePath($vArcFile);
        if (\file_exists($path)) {
            $data    = \json_decode(\file_get_contents($path), true);
            $changed = false;
            foreach ($data as &$row) {
                if (isset($row['user_plot']) && ! \is_int($row['user_plot'])) {
                    $row['user_plot'] = (int) $row['user_plot'];
                    $changed          = true;
                }
            }
            unset($row);
            if ($changed) {
                \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
            }
        }
    }
};
