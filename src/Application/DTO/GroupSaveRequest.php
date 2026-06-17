<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Speichern oder Erstellen von Berechtigungsgruppen.
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
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $groupId   = (string) ($post['group_id'] ?? '');
        $groupName = \trim((string) ($post['group_name'] ?? ''));
        $inherit   = (string) ($post['inherit_group'] ?? '');
        $perms     = (array) ($post['perms'] ?? []);

        if ($groupName === '') {
            throw ValidationException::withMessage('Fehler: Der Gruppenname darf nicht leer sein.');
        }

        return new self($groupId, $groupName, $inherit, $perms);
    }
}
