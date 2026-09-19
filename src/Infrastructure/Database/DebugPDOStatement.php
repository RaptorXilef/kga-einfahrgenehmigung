<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDOStatement;

/**
 * Debugging-Wrapper für PDOStatement.
 * Fängt execute() Aufrufe ab, um sie inkl. Parameter ins Log zu schreiben.
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
        $this->pdo->logQuery($this->queryString, $params ?? []);

        return parent::execute($params);
    }
}
