<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Database\SchemaRegistry;

/**
 * Fügt die fehlende neue Storniert-Tabelle hinzu.
 * Nutzt das zentrale Schema und beachtet individuelle Tabellennamen aus der Config.
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        // Tabellenname aus der Config laden (Fallback: cancelled_permits)
        $table = $config->get('storage_config')['cancelled_permits']['table'] ?? 'cancelled_permits';

        // Das zentrale SQL-Schema laden
        $baseSql = $config->get('db_schema')['cancelled_permits'] ?? SchemaRegistry::getSchemas()['cancelled_permits'];

        // Den Standard-Tabellennamen durch den konfigurierten Namen ersetzen
        $sql = \str_replace('`cancelled_permits`', "`{$table}`", $baseSql);

        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            \error_log('Migration 009 (MySQL): ' . $e->getMessage());
        }
    }
};
