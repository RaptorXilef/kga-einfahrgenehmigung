<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service für das passwortlose Benutzer-Login-Verfahren (Magic-Links / Login-Codes).
 *
 * Erstellt hochfeste Krypto-Token sowie kurze 6-stellige Codes, überwacht deren
 * Ablaufzeitfenster (TTL) und persistiert diese sitzungsübergreifend per JSON oder MySQL.
 * Kontext: Authentifizierungskomponente für Endbenutzer-Historienzugriffe.
 *
 * Path: src/Core/Service/MagicLinkService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MagicLinkService
{
    private string $storagePath;

    public function __construct(
        private ConfigInterface $config,
        private ?\PDO $pdo, // NEU
    ) {
        // storagePath wird für JSON weiter berechnet
        $cfg               = $this->config->get('storage_config')['magic_links'];
        $this->storagePath = $this->config->get('root_path') . '/' .
            $this->config->get('storage_path_prefix') . $cfg['file'];
    }

    /**
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

        $links    = $this->loadLinks();
        $duration = (int) $this->config->get('magic_link_duration', 15);

        $links[$token] = [
            'email'   => $email,
            'code'    => $code,
            'expires' => \time() + ($duration * 60),
        ];

        $this->saveLinks($links);

        return ['token' => $token, 'code' => $code];
    }

    /**
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
        $links      = $this->loadLinks();
        $now        = \time();
        $trimmed    = \trim($input);
        $foundEmail = null;

        foreach ($links as $token => $data) {
            // Passive Bereinigung abgelaufener Tokens
            if ($data['expires'] < $now) {
                unset($links[$token]);

                continue;
            }

            // NEU: Differenzierte Prüfung
            // 1. Vergleich gegen Lang-Token (Case-Insensitive für Hex)
            // 2. Vergleich gegen Kurz-Code (Immer Großbuchstaben)
            $isLongTokenMatch = \strlen($token) === \strlen($trimmed)
                && \hash_equals(\strtolower($token), \strtolower($trimmed));

            $isShortCodeMatch = isset($data['code'])
                && \strlen($data['code']) === \strlen($trimmed)
                && \hash_equals(\strtoupper($data['code']), \strtoupper($trimmed));

            if ($isLongTokenMatch || $isShortCodeMatch) {
                $foundEmail = $data['email'];
                unset($links[$token]); // Einmal-Nutzung

                break; // Schleife abbrechen, wir haben einen Treffer
            }
        }

        $this->saveLinks($links);

        return $foundEmail;
    }

    /**
     * Lädt den aktuellen Bestand an ungelösten Token-Referenzen aus dem konfigurierten Backend.
     *
     * @return array<string, array{email: string, code: string, expires: int}> Liste aktiver Tokens indiziert nach
     *                                                                         Krypto-Hash.
     */
    private function loadLinks(): array
    {
        $cfg = $this->config->get('storage_config')['magic_links'];
        if ($cfg['type'] === 'mysql') {
            $stmt  = $this->pdo->query("SELECT * FROM {$cfg['table']}");
            $rows  = $stmt->fetchAll();
            $links = [];
            foreach ($rows as $r) {
                $links[$r['token']] = [
                    'email'   => $r['email'],
                    'code'    => $r['code'],
                    'expires' => (int) $r['expires'],
                ];
            }

            return $links;
        }

        if (! \file_exists($this->storagePath)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($this->storagePath), true) ?? [];
    }

    /**
     * Persistiert die übergebene Token-Liste im aktiven Speicher-Subsystem (MySQL-Truncate/Insert oder JSON).
     *
     * @param array<string, array{email: string, code: string, expires: int}> $links Das zu schreibende Token-Array.
     */
    public function saveLinks(array $links): void
    {
        $cfg = $this->config->get('storage_config')['magic_links'];

        if ($cfg['type'] === 'mysql') {
            if (!$this->pdo instanceof \PDO) {
                throw new \RuntimeException('MySQL offline');
            }
            $this->pdo->exec("DELETE FROM {$cfg['table']}");
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$cfg['table']} (token, email, code, expires) VALUES (?, ?, ?, ?)",
            );
            foreach ($links as $token => $d) {
                $stmt->execute([$token, $d['email'], $d['code'], (int) $d['expires']]);
            }

            return;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($links, \JSON_PRETTY_PRINT));
    }
}
