<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Database;

use PDO;
use PDOStatement;

/**
 * Debugging-Wrapper für PDO.
 * Loggt alle ausgeführten SQL-Statements inkl. der Bindungs-Parameter und der Ausführungsdauer.
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

    public function logQuery(string $sql, array $params = [], ?float $durationMs = null): void
    {
        if (!isset($this->logFile)) {
            $this->logFile = \dirname(__DIR__, 4) . '/logs/sql_debug.log';
        }

        $logDir = \dirname($this->logFile);
        if (!\is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $timestamp = \date('Y-m-d H:i:s') . '.' . \sprintf('%03d', \fmod(\microtime(true), 1) * 1000);
        $durStr = $durationMs !== null ? \sprintf('[%.2f ms] ', $durationMs) : '[N/A ms] ';
        $paramString = $params !== [] ? ' | Params: ' . \json_encode($params, \JSON_UNESCAPED_UNICODE) : '';

        $msg = "[$timestamp] $durStr$sql$paramString\n";

        @\file_put_contents($this->logFile, $msg, \FILE_APPEND | \LOCK_EX);
    }

    public function exec(string $statement): int|false
    {
        $start = \microtime(true);
        $result = parent::exec($statement);
        $duration = (\microtime(true) - $start) * 1000;

        $this->logQuery($statement, [], $duration);

        return $result;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $start = \microtime(true);
        $result = parent::query($query, $fetchMode, ...$fetchModeArgs);
        $duration = (\microtime(true) - $start) * 1000;

        $this->logQuery($query, [], $duration);

        return $result;
    }
}
