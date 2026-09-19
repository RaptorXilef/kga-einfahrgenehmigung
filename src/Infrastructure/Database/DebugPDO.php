<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOStatement;

/**
 * Debugging-Wrapper für PDO.
 * Loggt alle ausgeführten SQL-Statements inkl. der Bindungs-Parameter in eine Datei.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
class DebugPDO extends PDO
{
    private string $logFile;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        // Wir weisen PDO an, für Statements unsere Wrapper-Klasse zu verwenden
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPDOStatement::class, [$this]]);
    }

    public function setLogFile(string $path): void
    {
        $this->logFile = $path;
    }

    public function logQuery(string $sql, array $params = []): void
    {
        if (!isset($this->logFile)) {
            $this->logFile = \dirname(__DIR__, 3) . '/logs/sql_debug.log';
        }

        $logDir = \dirname($this->logFile);
        if (!\is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        // Millisekunden-genauer Timestamp
        $timestamp = \date('Y-m-d H:i:s') . '.' . \sprintf('%03d', \fmod(\microtime(true), 1) * 1000);
        $paramString = $params !== [] ? ' | Params: ' . \json_encode($params, \JSON_UNESCAPED_UNICODE) : '';
        $msg = "[$timestamp] $sql$paramString\n";

        @\file_put_contents($this->logFile, $msg, \FILE_APPEND | \LOCK_EX);
    }

    public function exec(string $statement): int|false
    {
        $this->logQuery($statement);

        return parent::exec($statement);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->logQuery($query);

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
