<?php

declare(strict_types=1);

namespace App\Contracts\Security;

// TODO DOCBLOCK
interface RateLimiterInterface
{
    // TODO DOCBLOCK
    public function isBlocked(string $ip): bool;

    // TODO DOCBLOCK
    public function recordFailedAttempt(string $ip): void;

    // TODO DOCBLOCK
    public function clearAttempts(string $ip): void;
}
