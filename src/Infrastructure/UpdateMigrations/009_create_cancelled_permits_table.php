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
        // Tabellenname aus der Config laden (Fallback: permits_cancelled)
        $table = $config->get('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled';

        // Das zentrale SQL-Schema laden
        $baseSql = $config->get('db_schema')['permits_cancelled'] ?? SchemaRegistry::getSchemas()['permits_cancelled'];

        // Den Standard-Tabellennamen durch den konfigurierten Namen ersetzen
        $sql = \str_replace('`permits_cancelled`', "`{$table}`", $baseSql);

        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            \error_log('Migration 009 (MySQL): ' . $e->getMessage());
        }
    }
};
