<?php

declare(strict_types=1);

namespace App\Contracts\Config;

/**
 * Interface für den Zugriff auf Anwendungseinstellungen.
 */
interface ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function getBaseUrl(): string;

    public function isTestMode(): bool;

    public function getPriceForType(string $type): float;

    /**
     * @return array<string, mixed>
     */
    public function getMailSettings(): array;
}
