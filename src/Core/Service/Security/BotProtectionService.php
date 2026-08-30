<?php

declare(strict_types=1);

namespace App\Core\Service\Security;

use App\Contracts\Security\RateLimiterInterface;
use InvalidArgumentException;

/**
 * Service zur Abwehr von automatisierten Formular-Einsendungen (Bots).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class BotProtectionService
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    /**
     * Prüft, ob die IP-Adresse aufgrund zu vieler Anfragen blockiert ist.
     *
     * @throws InvalidArgumentException
     */
    public function checkRateLimit(string $ip): void
    {
        if ($this->rateLimiter->isBlocked($ip)) {
            throw new InvalidArgumentException('Zu viele Anträge in kurzer Zeit. Zu Ihrem Schutz wurde diese Funktion für 15 Minuten gesperrt.');
        }
    }

    /**
     * Zählt einen durchgeführten Antrag als "Strike" gegen das IP-Limit.
     */
    public function recordStrike(string $ip): void
    {
        $this->rateLimiter->recordFailedAttempt($ip);
    }

    /**
     * Prüft, ob das Formular in unmenschlicher Geschwindigkeit (Millisekunden) ausgefüllt wurde.
     *
     * @throws InvalidArgumentException
     */
    public function verifyTimeCheck(int $startTime, int $minSeconds = 3): void
    {
        if ($startTime === 0) {
            throw new InvalidArgumentException('Sicherheits-Token abgelaufen: Bitte laden Sie die Seite neu und füllen Sie das Formular erneut aus.');
        }

        $duration = \time() - $startTime;
        if ($duration < $minSeconds) {
            throw new InvalidArgumentException('Das Formular wurde zu schnell ausgefüllt (Bot-Verdacht). Ein Mensch benötigt dafür mehr Zeit.');
        }
    }

    /**
     * Prüft das unsichtbare Honeypot-Feld.
     *
     * @throws InvalidArgumentException
     */
    public function verifyHoneypot(string $honeypotValue): void
    {
        if (\trim($honeypotValue) !== '') {
            throw new InvalidArgumentException('Spam-Schutz aktiviert: Ungültige Formularanfrage erkannt.');
        }
    }
}
