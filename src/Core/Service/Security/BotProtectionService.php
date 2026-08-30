<?php

declare(strict_types=1);

namespace App\Core\Service\Security;

use InvalidArgumentException;

/**
 * Service zur Abwehr von automatisierten Formular-Einsendungen (Bots).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class BotProtectionService
{
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
