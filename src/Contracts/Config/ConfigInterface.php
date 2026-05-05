<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Contracts/Config/ConfigInterface.php
 */

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
