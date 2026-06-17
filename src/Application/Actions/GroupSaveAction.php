<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/GroupSaveAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupSaveAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Erstellt eine neue Benutzergruppe oder aktualisiert bestehende Rechte-Zuordnungen.
     * Aktualisiert die Session-Rechte zur Laufzeit, falls die eigene Gruppe modifiziert wurde.
     *
     * @param array<string, mixed> $post Rechte- und Gruppendaten.
     *
     * @return string Operations-Ergebnistext.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $groups = $this->groupRepository->loadAll();

        $groupId     = (string) ($post['group_id'] ?? '');
        $isUpdate    = $groupId !== '' && isset($groups[$groupId]);
        $displayName = \trim((string) ($post['group_name'] ?? ''));
        $inheritFrom = (string) ($post['inherit_group'] ?? '');

        if (! $isUpdate) {
            do {
                $groupId = $this->auth->generateId('grp_');
            } while (isset($groups[$groupId]));
        }

        $newPermissions = (array) ($post['perms'] ?? []);
        if (! $isUpdate && $inheritFrom !== '' && isset($groups[$inheritFrom])) {
            $newPermissions = $groups[$inheritFrom]['permissions'];
        }

        $groups[$groupId] = ['name' => $displayName, 'permissions' => $newPermissions];
        $this->groupRepository->saveAll($groups);

        $iconFile = $_FILES['group_icon'] ?? ($_FILES['avatar'] ?? null);
        if ($iconFile && $iconFile['error'] === 0) {
            $this->groupRepository->uploadImage($groupId, $iconFile);
        }

        if ($isUpdate) {
            if ($this->auth->getGroup() === $groupId) {
                $this->auth->refreshSessionPermissions($groupId);
            }

            return "Rechte für Gruppe '$displayName' erfolgreich aktualisiert.";
        }

        return "Neue Gruppe '$displayName' wurde erstellt.";
    }
}
