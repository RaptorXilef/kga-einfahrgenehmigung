<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

interface CancelledPermitRepositoryInterface
{
    public function findByHash(string $hash): ?Permit;

    public function saveCancelled(Permit $permit): void;

    public function isCodeCancelled(string $code): bool;

    public function loadAll(): array;
}
