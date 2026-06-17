<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Umbenennen einer Berechtigungsgruppe.
 *
 * Path: src/Application/DTO/GroupRenameRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupRenameRequest
{
    private function __construct(
        public string $groupId,
        public string $newGroupName,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $groupId = (string) ($post['group_id'] ?? '');
        $newName = \trim((string) ($post['new_group_name'] ?? ''));

        if ($groupId === '') {
            throw ValidationException::withMessage('Fehler: Keine Gruppe ausgewählt.');
        }
        if ($newName === '') {
            throw ValidationException::withMessage('Fehler: Der neue Gruppenname darf nicht leer sein.');
        }

        return new self($groupId, $newName);
    }
}
