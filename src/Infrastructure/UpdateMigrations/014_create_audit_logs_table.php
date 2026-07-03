<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Database\SchemaRegistry;

/**
 * Erstellt die neue Tabelle für die Nutzerprotokolle
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $table   = $config->get('storage_config')['audit_logs']['table'] ?? 'audit_logs';
        $baseSql = $config->get('db_schema')['audit_logs'] ?? SchemaRegistry::getSchemas()['audit_logs'];

        $sql = \str_replace('`audit_logs`', "`{$table}`", $baseSql);

        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            \error_log('Migration 014 (MySQL): ' . $e->getMessage());
        }
    }
};
