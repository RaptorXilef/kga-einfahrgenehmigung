<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\LoginAttemptRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\LoginAttempt;

/**
 * Implementierung des Rate-Limiters zum Schutz vor Brute-Force Logins.
 *
 * Speichert Fehlversuche je IP-Adresse (in MySQL oder JSON) und sperrt den
 * Zugang temporär nach Überschreiten der definierten Limits (Lockout-Time).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class RateLimiter implements RateLimiterInterface
{
    private const int MAX_ATTEMPTS    = 5;
    private const int LOCKOUT_MINUTES = 15;

    public function __construct(
        private ClockInterface $clock,
        private LoginAttemptRepositoryInterface $repository,
    ) {
    }

    // --- Public API --

    /**
     * Prüft, ob die IP-Adresse blockiert ist, da die maximalen Fehlversuche überschritten wurden.
     * Gibt die IP automatisch nach Ablauf der Lockout-Zeit wieder frei.
     *
     * @param string $ip Die zu prüfende IP-Adresse.
     *
     * @return bool True, wenn die IP gesperrt ist.
     */
    public function isBlocked(string $ip): bool
    {
        $attempt = $this->repository->findByIp($ip);
        if (! $attempt) {
            return false;
        }

        $now         = $this->clock->now();
        $diffMinutes = ($now->getTimestamp() - $attempt->lastAttempt->getTimestamp()) / 60;

        if ($diffMinutes > self::LOCKOUT_MINUTES) {
            $this->clearAttempts($ip);

            return false;
        }

        return $attempt->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Registriert einen neuen Fehlversuch für die gegebene IP-Adresse.
     * Erhöht den Zähler oder aktualisiert den Zeitstempel und bereinigt alte Einträge.
     *
     * @param string $ip Die betroffene IP-Adresse.
     */
    public function recordFailedAttempt(string $ip): void
    {
        $this->repository->deleteOlderThan(self::LOCKOUT_MINUTES);

        $attempt  = $this->repository->findByIp($ip);
        $attempts = $attempt ? $attempt->attempts + 1 : 1;

        $this->repository->save(new LoginAttempt($ip, $attempts, $this->clock->now()));
    }

    /**
     * Löscht alle registrierten Fehlversuche für eine IP-Adresse.
     * Wird nach einem erfolgreichen Login oder nach Ablauf der Sperrzeit aufgerufen.
     *
     * @param string $ip Die betroffene IP-Adresse.
     */
    public function clearAttempts(string $ip): void
    {
        $this->repository->deleteByIp($ip);
    }
}
