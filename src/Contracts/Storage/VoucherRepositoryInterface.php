<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

// TODO DocBlock
interface VoucherRepositoryInterface
{
    public function loadAll(): array;

    public function saveAll(array $vouchers, bool $forceSql = false): void;

    public function loadArchive(): array;

    public function appendToArchive(array $archiveEntry): void;
}
