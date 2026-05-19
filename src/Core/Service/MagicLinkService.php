<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Core/Service/MagicLinkService.php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Dieser neue Service verwaltet die temporären Token für den Login.
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
        $this->storagePath = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
    }

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
                && \strlen((string) $data['code']) === \strlen($trimmed)
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
     * @return array<string, array{email: string, expires: int}>
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
     * Macht den Service kompatibel für die Migration
     *
     * @param array<string, array{email: string, code: string, expires: int}> $links
     */
    public function saveLinks(array $links): void
    {
        $cfg = $this->config->get('storage_config')['magic_links'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo) {
                throw new \RuntimeException('MySQL offline');
            }
            $this->pdo->exec("DELETE FROM {$cfg['table']}");
            $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (token, email, code, expires) VALUES (?, ?, ?, ?)");
            foreach ($links as $token => $d) {
                $stmt->execute([$token, $d['email'], $d['code'], (int) $d['expires']]);
            }

            return;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($links, \JSON_PRETTY_PRINT));
    }
}
