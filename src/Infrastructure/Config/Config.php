<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Zentrales Konfigurations-Objekt.
 *
 * Verwaltet alle Anwendungseinstellungen und ermöglicht den Zugriff auf
 * Mail-Templates und Provider-Daten.
 *
 * @file      src/Infrastructure/Config/Config.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Contracts\Config\ConfigInterface;

/**
 * @immutable
 */
final readonly class Config implements ConfigInterface
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private array $settings,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMailSettings(): array
    {
        // Wir casten auf array, damit PHPStan sicher ist, dass wir das Interface erfüllen
        return (array) $this->get('mail', []);
    }

    public function isTestMode(): bool
    {
        return (bool) $this->get('test_mode', true);
    }

    public function getPermitDuration(): int
    {
        // Standardmäßig 5 Tage, falls nichts in der config.php steht
        return (int) $this->get('permit_duration', 5);
    }

    public function getPriceForType(string $type): float
    {
        /** @var array<string, float> $prices */
        $prices = $this->get('prices', [
            'pkw' => 3.00,
            'lkw' => 3.00, // Fallback
        ]);

        return (float) ($prices[$type] ?? $prices['pkw']);
    }

    public function getBaseUrl(): string
    {
        // Falls in Config gesetzt, nimm die, sonst erkenne sie automatisch
        $configured = $this->get('base_url');
        if ($configured !== null && $configured !== '') {
            return \rtrim((string) $configured, '/') . '/';
        }

        // Fallback für CLI/Cron-Jobs, wo $_SERVER['HTTPS'] fehlt
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        $host     = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $path     = \rtrim(\dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');

        // Fix für API-Aufrufe (wenn wir im Unterordner /api/ sind)
        $path = \str_replace('/api', '', $path);

        return $protocol . $host . $path . '/';
    }
}
