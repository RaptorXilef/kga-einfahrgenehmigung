<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\MagicLink;

/**
 * Interface für das Speicher-Repository von Magic-Links.
 * Handhabt die temporären Tokens für passwortlose E-Mail-Logins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface MagicLinkRepositoryInterface
{
    /**
     * Lädt alle aktiven Magic-Links.
     *
     * @return MagicLink[]
     */
    public function loadAll(): array;

    /**
     * Speichert alle Magic-Links.
     *
     * @param MagicLink[] $links
     * @param bool        $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function saveAll(array $links, bool $forceSql = false): void;

    public function import(array $data): void;
}
