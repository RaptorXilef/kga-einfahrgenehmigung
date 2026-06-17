<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/GroupRenameAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupRenameAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private GroupRepositoryInterface $groupRepository)
    {
    }

    /**
     * Ändert den Anzeigenamen einer spezifischen Gruppe im System.
     *
     * @param array<string, mixed> $post Datensatz mit group_id und new_group_name.
     *
     * @return string Statusnachricht.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $gid     = (string) ($post['group_id'] ?? '');
        $newName = \trim((string) ($post['new_group_name'] ?? ''));
        if ($gid === '' || $newName === '') {
            return '';
        }

        $groups = $this->groupRepository->loadAll();
        if (! isset($groups[$gid])) {
            return 'Fehler: Gruppe nicht gefunden.';
        }

        $groups[$gid]['name'] = $newName;
        $this->groupRepository->saveAll($groups);

        return "Gruppe wurde in '$newName' umbenannt.";
    }
}
