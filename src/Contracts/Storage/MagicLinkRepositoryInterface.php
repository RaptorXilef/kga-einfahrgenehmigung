<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Interface für das Speicher-Repository von Magic-Links.
 * Handhabt die temporären Tokens für passwortlose E-Mail-Logins.
 *
 * Path: src/Contracts/Storage/MagicLinkRepositoryInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface MagicLinkRepositoryInterface
{
    /**
     * Lädt alle aktiven Magic-Links.
     *
     * @return array<string, array<string, mixed>> Die gespeicherten Magic-Links.
     */
    public function loadAll(): array;

    /**
     * Speichert alle Magic-Links.
     *
     * @param array<string, array<string, mixed>> $links    Die zu speichernden Links.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function saveAll(array $links, bool $forceSql = false): void;
}
