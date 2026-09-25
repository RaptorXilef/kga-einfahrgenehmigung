<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Services;

use App\Contracts\Security\BotProtectionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Utils\ClockInterface;
use InvalidArgumentException;
use Override;

/**
 * Service zur Abwehr von automatisierten Formular-Einsendungen (Bots).
 */
final readonly class BotProtectionService implements BotProtectionInterface
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Prüft, ob die IP-Adresse aufgrund zu vieler Anfragen blockiert ist.
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function checkRateLimit(string $ip): void
    {
        if ($this->rateLimiter->isBlocked($ip)) {
            throw new InvalidArgumentException('Zu viele Anträge in kurzer Zeit. Zu Ihrem Schutz wurde diese Funktion für 15 Minuten gesperrt.');
        }
    }

    /**
     * Zählt einen durchgeführten Antrag als "Strike" gegen das IP-Limit.
     */
    #[Override]
    public function recordStrike(string $ip): void
    {
        $this->rateLimiter->recordFailedAttempt($ip);
    }

    /**
     * Prüft, ob das Formular in unmenschlicher Geschwindigkeit (Millisekunden) ausgefüllt wurde.
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function verifyTimeCheck(int $startTime, int $minSeconds = 3): void
    {
        if ($startTime === 0) {
            throw new InvalidArgumentException('Sicherheits-Token abgelaufen: Die Seite wurde zur Sicherheit neu geladen. Bitte senden Sie den Antrag über die Schaltfläche unten erneut ab.');
        }

        $duration = $this->clock->now()->getTimestamp() - $startTime;
        if ($duration < $minSeconds) {
            throw new InvalidArgumentException('Das Formular wurde zu schnell ausgefüllt (Bot-Verdacht). Ein Mensch benötigt dafür normalerweise mehr Zeit.');
        }
    }

    /**
     * Prüft das unsichtbare Honeypot-Feld.
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function verifyHoneypot(string $honeypotValue): void
    {
        if (\trim($honeypotValue) !== '') {
            throw new InvalidArgumentException('Spam-Schutz aktiviert: Ungültige Formularanfrage erkannt.');
        }
    }
}
