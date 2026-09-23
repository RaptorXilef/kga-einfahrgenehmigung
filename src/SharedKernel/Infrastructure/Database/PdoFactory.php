<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Database;

use App\Contracts\Config\ConfigInterface;
use PDO;
use PDOException;

/**
 * Factory zur Erstellung der zentralen PDO-Datenbankverbindung.
 *
 * Kapselt die komplexe Logik des Verbindungsaufbaus, das automatische Anlegen
 * fehlender Datenbanken (Auto-Setup) und das Ausrollen des initialen Tabellenschemas.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class PdoFactory
{
    /**
     * Erstellt und konfiguriert die PDO-Instanz.
     *
     * @param ConfigInterface $config Die Systemkonfiguration.
     *
     * @return PDO|null Die aktive Verbindung oder null, wenn MySQL deaktiviert ist oder fehlschlägt.
     */
    public static function create(ConfigInterface $config): ?PDO
    {
        $db = $config->get('database', []);

        if (!isset($db['enabled']) || $db['enabled'] === false) {
            return null;
        }

        $portStr = !empty($db['port']) ? ";port={$db['port']}" : '';
        $dsnWithDb = "mysql:host={$db['host']}{$portStr};dbname={$db['dbname']};charset={$db['charset']}";

        // Dynamischer Switch zwischen echtem PDO und dem Logging-Wrapper
        $isDebugMode = $config->get('debug_mode', false) === true;
        $pdoClass = $isDebugMode ? DebugPDO::class : PDO::class;
        $pdo = null;

        try {
            $pdo = new $pdoClass($dsnWithDb, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2,
            ]);

            if ($pdo instanceof DebugPDO) {
                $logPath = \rtrim((string) $config->get('root_path', ''), '/\\') . '/logs/sql_debug.log';
                $pdo->setLogFile($logPath);
            }
        } catch (PDOException $e) {
            $mysqlErrorCode = $e->errorInfo[1] ?? null;

            if ($mysqlErrorCode !== 1049) {
                \error_log('MySQL Connection Error: ' . $e->getMessage());

                return null;
            }

            $dsnWithoutDb = "mysql:host={$db['host']}{$portStr};charset={$db['charset']}";

            try {
                $pdo = new $pdoClass($dsnWithoutDb, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 2,
                ]);

                if ($pdo instanceof DebugPDO) {
                    $logPath = \rtrim((string) $config->get('root_path', ''), '/\\') . '/logs/sql_debug.log';
                    $pdo->setLogFile($logPath);
                }

                $sql = "CREATE DATABASE IF NOT EXISTS `{$db['dbname']}` " .
                    "CHARACTER SET {$db['charset']} COLLATE {$db['charset']}_unicode_ci";

                $pdo->exec($sql);
                $pdo->exec("USE `{$db['dbname']}`");
            } catch (PDOException $e2) {
                \error_log('MySQL Auto-Install Error (DB Create): ' . $e2->getMessage());

                return null;
            }
        }

        return $pdo;
    }
}
