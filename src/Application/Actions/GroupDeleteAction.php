<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/GroupDeleteAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupDeleteAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private ConfigInterface $config, private GroupRepositoryInterface $groupRepository)
    {
    }

    /**
     * Löscht eine Gruppe aus dem Berechtigungssystem. Schützt die Kern-Gruppe 'admin'.
     *
     * @param array<string, mixed> $post Datensatz mit group_id.
     *
     * @return string Ergebnisnachricht.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $id = (string) ($post['group_id'] ?? '');
        if ($id === 'admin') {
            return 'Fehler: Die Admin-Gruppe kann nicht gelöscht werden.';
        }
        if (\str_contains($id, '://') || \str_contains($id, '..') || \str_contains($id, "\0")) {
            return 'Fehler: Ungültige Gruppen-ID.';
        }

        $groups = $this->groupRepository->loadAll();
        if (isset($groups[$id])) {
            unset($groups[$id]);
            $this->groupRepository->saveAll($groups);

            $iconPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/group_images/' . $id . '.webp';
            if (\file_exists($iconPath)) {
                @\unlink($iconPath);
            }

            return 'Gruppe gelöscht.';
        }

        return 'Fehler: Gruppe nicht gefunden.';
    }
}
