<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

// TODO DocBlock
interface VerificationRepositoryInterface
{
    public function loadPending(): array;

    public function savePending(array $data, bool $forceSql = false): void;

    public function loadVerified(): array;

    public function saveVerified(array $data, bool $forceSql = false): void;
}
