<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

interface VerificationRepositoryInterface
{
    /**
     * @return VerificationRequest[]
     */
    public function loadPending(): array;

    /**
     * @param VerificationRequest[] $data
     */
    public function savePending(array $data, bool $forceSql = false): void;

    /**
     * @return VerificationRequest[]
     */
    public function loadVerified(): array;

    /**
     * @param VerificationRequest[] $data
     */
    public function saveVerified(array $data, bool $forceSql = false): void;
}
