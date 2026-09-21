<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

interface PermitRepositoryInterface
{
    public function save(Permit $permit): void;

    public function findByCode(string $code): ?Permit;

    public function delete(string $code): void;
}
