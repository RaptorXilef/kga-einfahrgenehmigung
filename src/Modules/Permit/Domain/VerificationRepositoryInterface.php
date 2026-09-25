<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

/**
 * Vertrag für die Verwaltung von Double-Opt-In- und Checkout-Sitzungen.
 */
interface VerificationRepositoryInterface
{
    /**
     * @return array<string, VerificationRequest>
     */
    public function loadPending(): array;

    public function findPendingByTokenOrCode(string $tokenOrCode): ?VerificationRequest;

    public function savePendingOne(VerificationRequest $request): void;

    public function deletePending(string $token): void;

    public function findVerifiedByToken(string $token): ?VerificationRequest;

    public function findVerifiedByTokenOrCode(string $tokenOrCode): ?VerificationRequest;

    public function saveVerifiedOne(VerificationRequest $request): void;

    public function deleteVerified(string $token): void;
}
