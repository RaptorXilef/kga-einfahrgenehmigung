<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Dieser neue Service verwaltet die temporären Token für den Login.
 *
 * @file src/Core/Service/MagicLinkService.php
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

final readonly class MagicLinkService
{
    private string $storagePath;

    public function __construct(private ConfigInterface $config)
    {
        $this->storagePath = $this->config->get('root_path') . '/storage/magic_links.json';
    }

    public function createToken(string $email): string
    {
        $token = \bin2hex(\random_bytes(32));
        $links = $this->loadLinks();

        $duration      = (int) $this->config->get('magic_link_duration', 15);
        $links[$token] = [
            'email'   => $email,
            'expires' => \time() + ($duration * 60),
        ];

        $this->saveLinks($links);

        return $token;
    }

    public function verifyToken(string $token): ?string
    {
        $links = $this->loadLinks();
        if (! isset($links[$token]) || $links[$token]['expires'] < \time()) {
            return null;
        }

        $email = $links[$token]['email'];
        // Einmal-Prinzip: Link nach Nutzung löschen
        unset($links[$token]);
        $this->saveLinks($links);

        return $email;
    }

    /**
     * @return array<string, array{email: string, expires: int}>
     */
    private function loadLinks(): array
    {
        if (! \file_exists($this->storagePath)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($this->storagePath), true) ?? [];
    }

    /**
     * @param array<string, array{email: string, expires: int}> $links
     */
    private function saveLinks(array $links): void
    {
        \file_put_contents($this->storagePath, \json_encode($links, \JSON_PRETTY_PRINT));
    }
}
