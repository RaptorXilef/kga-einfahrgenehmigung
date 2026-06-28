<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Permit;

interface CancelledPermitRepositoryInterface
{
    public function saveCancelled(Permit $permit): void;

    public function isCodeCancelled(string $code): bool;

    public function loadAll(): array;
}
