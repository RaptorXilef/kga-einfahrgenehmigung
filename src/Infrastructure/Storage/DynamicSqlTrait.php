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
        $columns = \array_keys($data);
        $colString = \implode(', ', \array_map(fn (int|string $c): string => "`$c`", $columns));
        $valString = \implode(', ', \array_map(fn (int|string $c): string => ":$c", $columns));
        $updString = \implode(', ', \array_map(fn (int|string $c): string => "`$c` = VALUES(`$c`)", $columns));

        return "INSERT INTO `{$table}` ($colString) VALUES ($valString) ON DUPLICATE KEY UPDATE $updString";
    }

    /**
     * Generiert automatisch ein REPLACE INTO Statement.
     */
    protected function buildReplaceSql(string $table, array $data): string
    {
        $columns = \array_keys($data);
        $colString = \implode(', ', \array_map(fn (int|string $c): string => "`$c`", $columns));
        $valString = \implode(', ', \array_map(fn (int|string $c): string => ":$c", $columns));

        return "REPLACE INTO `{$table}` ($colString) VALUES ($valString)";
    }

    /**
     * Generiert und führt ein dynamisches UPSERT (Insert on Duplicate Key Update) aus.
     *
     * @param string $table Der Name der Tabelle.
     * @param array<string, mixed> $data Assoziatives Array mit Spaltennamen als Keys und Werten.
     * @param array<int, string> $excludeUpdate Array mit Spaltennamen, die beim UPDATE ignoriert werden.
     */
    protected function executeUpsert(string $table, array $data, array $excludeUpdate = ['id']): bool
    {
        if ($data === []) {
            return false;
        }

        $columns = \array_keys($data);
        $colString = \implode(', ', \array_map(fn (string $col): string => "`$col`", $columns));
        $valString = \implode(', ', \array_map(fn (string $col): string => ":$col", $columns));

        $updateCols = \array_filter($columns, fn (string $col): bool => !\in_array($col, $excludeUpdate, true));

        $updString = \implode(
            ', ',
            \array_map(fn (string $col): string => "`$col` = VALUES(`$col`)", $updateCols),
        );

        $sql = "INSERT INTO `$table` ($colString) VALUES ($valString)";
        if ($updString !== '' && $updString !== '0') {
            $sql .= " ON DUPLICATE KEY UPDATE $updString";
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($data);
    }
}
