<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\MagicLink;

/**
 * Service für das passwortlose Benutzer-Login-Verfahren (Magic-Links / Login-Codes).
 *
 * Erstellt hochfeste Krypto-Token sowie kurze 6-stellige Codes, überwacht deren
 * Ablaufzeitfenster (TTL) und persistiert diese sitzungsübergreifend per JSON oder MySQL.
 * Kontext: Authentifizierungskomponente für Endbenutzer-Historienzugriffe.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MagicLinkService
{
    private string $storagePath;

    public function __construct(
        private ClockInterface $clock,
        private ConfigInterface $config,
        private MagicLinkRepositoryInterface $repository,
    ) {
        // storagePath wird für JSON weiter berechnet
        $cfg               = $this->config->get('storage_config')['magic_links'];
        $this->storagePath = $this->config->getStoragePath($cfg['file']);
    }

    /**
     * Schritt 1: Token generieren und per Mail senden
     *
     * Generiert ein neues passwortloses Login-Paket für eine E-Mail-Adresse.
     * Erzeugt ein SHA-256 fähiges Lang-Token und einen kurzen alphanumerischen Code mit konfigurierter TTL.
     *
     * @param string $email Die Ziel-E-Mail-Adresse des Nutzers.
     *
     * @return array{token: string, code: string} Assoziatives Array aus Lang-Token und Kurz-Code.
     */
    public function createToken(string $email): array
    {
        $token = \bin2hex(\random_bytes(32));
        // Kurzer, gut lesbarer Code für manuelle Eingabe
        $code = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));

        $links    = $this->repository->loadAll();
        $duration = (int) $this->config->get('magic_link_duration', 15);

        $links[$token] = new MagicLink(
            $token,
            $email,
            $code,
            $this->clock->now()->modify("+{$duration} minutes"),
        );

        $this->repository->saveAll($links);

        return ['token' => $token, 'code' => $code];
    }

    /**
     * Schritt 2: Token/Code validieren und einloggen
     *
     * Überprüft eine Benutzereingabe gegen offene Login-Tokens oder Kurz-Codes.
     * Verwendet 'hash_equals' gegen Timing-Attacks, bereinigt abgelaufene Einträge (Garbage Collection)
     * und löscht verbrauchte Tokens sofort nach erfolgreichem Treffer (Single-Use-Garantie).
     *
     * @param string $input Das eingegebene Kurz-Code-Fragment oder das vollständige URL-Token.
     *
     * @return string|null Die E-Mail-Adresse des authentifizierten Inhabers oder null bei Ungültigkeit.
     */
    public function verifyAny(string $input): ?string
    {
        $links      = $this->repository->loadAll();
        $now        = $this->clock->now();
        $trimmed    = \trim($input);
        $foundEmail = null;

        foreach ($links as $token => $magicLink) {
            // Passive Bereinigung abgelaufener Tokens
            if ($magicLink->isExpired($now)) {
                unset($links[$token]);

                continue;
            }

            // Differenzierte Prüfung
            // 1. Vergleich gegen Lang-Token (Case-Insensitive für Hex)
            // 2. Vergleich gegen Kurz-Code (Immer Großbuchstaben)
            $isLongTokenMatch = \strlen($token) === \strlen($trimmed)
                && \hash_equals(\strtolower($token), \strtolower($trimmed));
            $isShortCodeMatch = \strlen($magicLink->code) === \strlen($trimmed)
                && \hash_equals(\strtoupper($magicLink->code), \strtoupper($trimmed));

            if ($isLongTokenMatch || $isShortCodeMatch) {
                $foundEmail = $magicLink->email;
                unset($links[$token]); // Einmal-Nutzung

                break; // Schleife abbrechen, wir haben einen Treffer
            }
        }

        $this->repository->saveAll($links);

        return $foundEmail;
    }
}
