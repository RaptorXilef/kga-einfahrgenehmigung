<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Database;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
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
     */
    public static function create(ConfigInterface $config, ?ClockInterface $clock = null): ?PDO
    {
        $db = $config->getArray('database');

        if (!isset($db['enabled']) || $db['enabled'] === false) {
            return null;
        }

        $portRaw = isset($db['port']) ? \trim((string) $db['port']) : '';
        $portStr = $portRaw !== '' ? ";port={$portRaw}" : '';
        $dsnWithDb = "mysql:host={$db['host']}{$portStr};dbname={$db['dbname']};charset={$db['charset']}";

        // Dynamischer Switch zwischen echtem PDO und dem Logging-Wrapper
        $isDebugMode = $config->getBool('debug_mode', false);
        $pdo = null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 2,
        ];

        try {
            // Nur dem DebugPDO das 5. Argument übergeben!
            if ($isDebugMode) {
                $pdo = new DebugPDO($dsnWithDb, (string) $db['user'], (string) $db['pass'], $options, $clock);
                $logPath = \rtrim($config->getString('root_path'), '/\\') . '/logs/sql_debug.log';
                $pdo->setLogFile($logPath);
            } else {
                $pdo = new PDO($dsnWithDb, (string) $db['user'], (string) $db['pass'], $options);
            }
        } catch (PDOException $e) {
            $mysqlErrorCode = $e->errorInfo[1] ?? null;

            if ($mysqlErrorCode !== 1049) {
                \error_log('MySQL Connection Error: ' . $e->getMessage());

                return null;
            }

            $dsnWithoutDb = "mysql:host={$db['host']}{$portStr};charset={$db['charset']}";

            try {
                if ($isDebugMode) {
                    $pdo = new DebugPDO(
                        $dsnWithoutDb,
                        (string) $db['user'],
                        (string) $db['pass'],
                        $options,
                        $clock,
                    );
                    $logPath = \rtrim($config->getString('root_path'), '/\\') . '/logs/sql_debug.log';
                    $pdo->setLogFile($logPath);
                } else {
                    $pdo = new PDO($dsnWithoutDb, (string) $db['user'], (string) $db['pass'], $options);
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
