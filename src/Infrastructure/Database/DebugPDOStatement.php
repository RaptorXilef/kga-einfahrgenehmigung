<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDOStatement;

/**
 * Debugging-Wrapper für PDOStatement.
 * Fängt execute() Aufrufe ab, um sie inkl. Parametern und der genauen Dauer ins Log zu schreiben.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
class DebugPDOStatement extends PDOStatement
{
    protected function __construct(protected DebugPDO $pdo)
    {
    }

    public function execute(?array $params = null): bool
    {
        $start = \microtime(true);
        $result = parent::execute($params);
        $duration = (\microtime(true) - $start) * 1000;

        $this->pdo->logQuery($this->queryString, $params ?? [], $duration);

        return $result;
    }
}
