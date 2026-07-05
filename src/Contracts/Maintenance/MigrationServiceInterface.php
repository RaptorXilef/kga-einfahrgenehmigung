<?php

declare(strict_types=1);

namespace App\Contracts\Maintenance;

interface MigrationServiceInterface
{
    public function execute(string $target, string $action): string;

    public function restore(string $timestamp, string $target, string $engine = 'all'): string;

    public function clearCache(): string;

    public function truncateTarget(string $target, string $engine = 'all'): string;
}
