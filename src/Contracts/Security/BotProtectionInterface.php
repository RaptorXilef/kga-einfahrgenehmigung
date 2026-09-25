<?php

declare(strict_types=1);

namespace App\Contracts\Security;

use InvalidArgumentException;

/**
 * Port für den Schutz öffentlicher Formulare vor Bots (Rate-Limit, Time-Trap, Honeypot).
 */
interface BotProtectionInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function checkRateLimit(string $ip): void;

    public function recordStrike(string $ip): void;

    /**
     * @throws InvalidArgumentException
     */
    public function verifyTimeCheck(int $startTime, int $minSeconds = 3): void;

    /**
     * @throws InvalidArgumentException
     */
    public function verifyHoneypot(string $honeypotValue): void;
}
