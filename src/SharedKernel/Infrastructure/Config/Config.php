<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Config;

use App\Contracts\Config\ConfigInterface;
use Override;

/**
 * Konfigurations-Infrastruktur-Provider der Anwendung.
 * Kapselt das aggregierte Einstellungs-Array und berechnet bei Bedarf dynamisch
 * die korrekten HTTPS-Basis-URLs sowie Tarifpreise für Fahrzeugtypen.
 *
 * @immutable
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class Config implements ConfigInterface
{
    /**
     * @param array<string, mixed> $settings Das rohe, zusammengeführte Konfigurations-Array.
     */
    public function __construct(
        private array $settings,
    ) {
    }

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    #[Override]
    public function getString(string $key, string $default = ''): string
    {
        $val = $this->get($key, $default);

        return \is_scalar($val) ? (string) $val : $default;
    }

    #[Override]
    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key, $default);

        return \is_numeric($val) ? (int) $val : $default;
    }

    #[Override]
    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key, $default);

        return \is_bool($val) ? $val : (bool) $val;
    }

    #[Override]
    public function getArray(string $key, array $default = []): array
    {
        $val = $this->get($key, $default);

        return \is_array($val) ? $val : $default;
    }

    #[Override]
    public function isTestMode(): bool
    {
        return $this->getBool('test_mode', true);
    }

    #[Override]
    public function getPriceForType(string $type): float
    {
        $vConfig = $this->getArray('vehicle_types');
        $defaultType = $vConfig === [] ? 'pkw' : (string) \array_key_first($vConfig);

        $prices = $this->getArray('prices');
        $price = $prices[$type] ?? ($prices[$defaultType] ?? 0.00);

        return \is_scalar($price) ? (float) $price : 0.00;
    }

    #[Override]
    public function getMailSettings(): array
    {
        $mail = $this->getArray($this->isTestMode() ? 'mail-test' : 'mail');

        if ($mail === []) {
            $mail = $this->getArray('mail');
        }

        return $mail;
    }

    #[Override]
    public function getBaseUrl(): string
    {
        $configured = $this->getString('base_url');
        if ($configured !== '') {
            return \rtrim($configured, '/');
        }

        $isCli = \php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST']);
        if ($isCli) {
            return \rtrim($this->getString('cli_fallback_url', 'http://localhost'), '/');
        }

        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $protocol = $isSecure ? 'https' : 'http';
        $host = \is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'localhost';

        return $protocol . '://' . $host;
    }

    #[Override]
    public function getStoragePath(string $fileName): string
    {
        $root = $this->getString('root_path');
        $prefix = $this->getString('storage_path_prefix');

        return \rtrim($root, '/\\') . '/' . \ltrim($prefix, '/\\') . \ltrim($fileName, '/\\');
    }
}
