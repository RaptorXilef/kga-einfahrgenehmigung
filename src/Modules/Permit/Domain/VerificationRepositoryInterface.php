<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

interface VerificationRepositoryInterface
{
    /**
     * @return array<string, VerificationRequest>
     */
    public function loadPending(): array;

    public function findPendingByTokenOrCode(string $tokenOrCode): ?VerificationRequest;

    public function savePendingOne(VerificationRequest $request): void;

    public function deletePending(string $token): void;

    /**
     * @param array<string, VerificationRequest> $data
     */
    public function savePending(array $data, bool $forceSql = false): void;

    /**
     * @return array<string, VerificationRequest>
     */
    public function loadVerified(): array;

    public function findVerifiedByToken(string $token): ?VerificationRequest;

    public function findVerifiedByTokenOrCode(string $tokenOrCode): ?VerificationRequest;

    public function saveVerifiedOne(VerificationRequest $request): void;

    public function deleteVerified(string $token): void;

    /**
     * @param array<string, VerificationRequest> $data
     */
    public function saveVerified(array $data, bool $forceSql = false): void;
}
