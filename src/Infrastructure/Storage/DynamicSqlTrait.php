<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

trait DynamicSqlTrait
{
    /**
     * Generiert automatisch ein sicheres INSERT ... ON DUPLICATE KEY UPDATE Statement.
     */
    protected function buildInsertUpdateSql(string $table, array $data): string
    {
        $columns   = \array_keys($data);
        $colString = \implode(', ', \array_map(fn ($c) => "`$c`", $columns));
        $valString = \implode(', ', \array_map(fn ($c) => ":$c", $columns));
        $updString = \implode(', ', \array_map(fn ($c) => "`$c` = VALUES(`$c`)", $columns));

        return "INSERT INTO `{$table}` ($colString) VALUES ($valString) ON DUPLICATE KEY UPDATE $updString";
    }

    /**
     * Generiert automatisch ein REPLACE INTO Statement.
     */
    protected function buildReplaceSql(string $table, array $data): string
    {
        $columns   = \array_keys($data);
        $colString = \implode(', ', \array_map(fn ($c) => "`$c`", $columns));
        $valString = \implode(', ', \array_map(fn ($c) => ":$c", $columns));

        return "REPLACE INTO `{$table}` ($colString) VALUES ($valString)";
    }
}
