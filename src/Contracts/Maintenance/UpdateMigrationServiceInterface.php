<?php

declare(strict_types=1);

namespace App\Contracts\Maintenance;

interface UpdateMigrationServiceInterface
{
    public function runAllPending(): array;

    public function import(array $data, bool $forceSql = false): void;
}
