<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Da die Tabellen in MySQL ja nun bereits als INT existieren, müssen wir sie für alle, die das Update laden
 * automatisch auf VARCHAR(50) ändern lassen, wenn das System bootet.
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $logTable   = $config->get('storage_config')['mail_log']['table'] ?? 'mail_logs';
        $queueTable = $config->get('storage_config')['mail_queue']['table'] ?? 'mail_queue';

        try {
            $pdo->exec("ALTER TABLE `{$logTable}` MODIFY COLUMN `id` VARCHAR(50) NOT NULL");
        } catch (\PDOException $e) {
            \error_log('Migration 012 (MySQL) Log Table: ' . $e->getMessage());
        }

        try {
            $pdo->exec("ALTER TABLE `{$queueTable}` MODIFY COLUMN `id` VARCHAR(50) NOT NULL");
        } catch (\PDOException $e) {
            \error_log('Migration 012 (MySQL) Queue Table: ' . $e->getMessage());
        }
    }
};
