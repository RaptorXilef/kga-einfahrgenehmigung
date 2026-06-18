<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Speichern oder Erstellen von Berechtigungsgruppen.
 * Kapselt POST-Daten und hochgeladene Datei-Strukturen.
 *
 * Path: src/Application/DTO/GroupSaveRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupSaveRequest
{
    private function __construct(
        public string $groupId,
        public string $groupName,
        public string $inheritGroup,
        public array $permissions,
        public ?array $groupIcon, // Gekapseltes Datei-Array
    ) {
    }

    public static function fromArray(array $post, array $files = []): self
    {
        $groupId   = (string) ($post['group_id'] ?? '');
        $groupName = \trim((string) ($post['group_name'] ?? ''));
        $inherit   = (string) ($post['inherit_group'] ?? '');
        $perms     = (array) ($post['perms'] ?? []);

        if ($groupName === '') {
            throw ValidationException::withMessage('Fehler: Der Gruppenname darf nicht leer sein.');
        }

        // Datei-Validierung direkt im DTO kapseln
        $iconFile      = $files['group_icon'] ?? ($files['avatar'] ?? null);
        $validatedIcon = null;
        if ($iconFile && isset($iconFile['error']) && $iconFile['error'] === 0) {
            $validatedIcon = $iconFile;
        }

        return new self(
            $groupId,
            $groupName,
            $inherit,
            $perms,
            $validatedIcon,
        );
    }
}
