<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Zentrales Konfigurations-Objekt.
 *
 * Verwaltet alle Anwendungseinstellungen und ermöglicht den Zugriff auf
 * Mail-Templates und Provider-Daten.
 *
 * @file      src/Infrastructure/Config/Config.php
 *
 * @since     0.1.0
 * - feat(config): Initialer Aufbau des Config-Service für OOP-Migration.
 */

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * @immutable
 */
final class Config
{
    public function __construct(
        private readonly array $settings
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function getMailSettings(): array
    {
        return $this->get('mail', []);
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
        $prices = $this->get('prices', [
        'pkw' => 3.00,
        'lkw' => 3.00, // Fallback
        ]);

        return (float) ($prices[$type] ?? $prices['pkw']);
    }
}
