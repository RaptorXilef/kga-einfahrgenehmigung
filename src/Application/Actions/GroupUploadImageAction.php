<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/GroupUploadImageAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupUploadImageAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Verarbeitet den Upload eines Bildes für Gruppen-Icons.
     *
     * @param array<string, mixed> $post Das Post-Array mit der group_id.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $file = $_FILES['avatar'] ?? null;
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }

        $gid = (string) ($post['group_id'] ?? '');

        return $this->groupRepository->uploadImage($gid, $file) ? 'Gruppen-Icon aktualisiert.' : 'Fehler beim Verarbeiten.';
    }
}
