<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Contracts\Config\ConfigInterface;

/**
 * Konfigurations-Infrastruktur-Provider der Anwendung.
 * Kapselt das aggregierte Einstellungs-Array und berechnet bei Bedarf dynamisch
 * die korrekten HTTPS-Basis-URLs sowie Tarifpreise für Fahrzeugtypen.
 * Kontext: Technische Implementierung des Config-Dienstes.
 *
 * Zentrales Konfigurations-Objekt.
 *
 * Verwaltet alle Anwendungseinstellungen und ermöglicht den Zugriff auf
 * Mail-Templates und Provider-Daten.
 *
 * @immutable
 *
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

    /**
     * Holt einen Wert direkt aus dem Einstellungs-Array.
     *
     * (Der wichtigste universelle Getter)
     *
     * @param string $key Der exakte Array-Schlüssel.
     * @param mixed $default Fallback bei Nichtexistenz.
     *
     * @return mixed Der gespeicherte Wert.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function isTestMode(): bool
    {
        return $this->get('test_mode', true) === true;
    }

    public function getPriceForType(string $type): float
    {
        $vConfigRaw = $this->get('vehicle_types', []);
        $vConfig = \is_array($vConfigRaw) ? $vConfigRaw : [];
        $defaultType = empty($vConfig) ? 'pkw' : (string) \array_key_first($vConfig);

        $pricesRaw = $this->get('prices', []);
        $prices = \is_array($pricesRaw) ? $pricesRaw : [];

        $price = $prices[$type] ?? ($prices[$defaultType] ?? 0.00);

        return \is_scalar($price) ? (float) $price : 0.00;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMailSettings(): array
    {
        $mail = $this->get('mail', []);
        if (!\is_array($mail)) {
            return [];
        }

        /** @var array<string, mixed> $mailArray */
        $mailArray = $mail;

        return $mailArray;
    }

    public function getBaseUrl(): string
    {
        $configured = $this->get('base_url');
        if (\is_string($configured) && $configured !== '') {
            return \rtrim($configured, '/');
        }

        $isCli = \php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST']);
        if ($isCli) {
            $fallbackRaw = $this->get('cli_fallback_url', 'http://localhost');
            $fallback = \is_string($fallbackRaw) ? $fallbackRaw : 'http://localhost';

            return \rtrim($fallback, '/');
        }

        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $protocol = $isSecure ? 'https' : 'http';
        $hostRaw = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = \is_string($hostRaw) ? $hostRaw : 'localhost';

        return $protocol . '://' . $host;
    }

    public function getStoragePath(string $fileName): string
    {
        $rootRaw = $this->get('root_path', '');
        $root = \is_string($rootRaw) ? $rootRaw : '';
        $prefixRaw = $this->get('storage_path_prefix', '');
        $prefix = \is_string($prefixRaw) ? $prefixRaw : '';

        return \rtrim($root, '/\\') . '/' . \ltrim($prefix, '/\\') . \ltrim($fileName, '/\\');
    }
}
