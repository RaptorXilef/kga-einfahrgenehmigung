<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\GroupSaveRequest;
use App\Application\Exception\ValidationException;
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

        try {
            $dto = GroupSaveRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $groups   = $this->groupRepository->loadAll();
        $isUpdate = $dto->groupId !== '' && isset($groups[$dto->groupId]);
        $groupId  = $dto->groupId;

        if (! $isUpdate) {
            do {
                $groupId = $this->auth->generateId('grp_');
            } while (isset($groups[$groupId]));
        }

        $newPermissions = $dto->permissions;

        // Vererbung anwenden
        if (! $isUpdate && $dto->inheritGroup !== '' && isset($groups[$dto->inheritGroup])) {
            $newPermissions = $groups[$dto->inheritGroup]['permissions'];
        }

        $groups[$groupId] = [
            'name'        => $dto->groupName,
            'permissions' => $newPermissions,
        ];

        $this->groupRepository->saveAll($groups);

        // Datei-Uploads lassen wir bewusst im $_FILES-Array, da DTOs nur für Textdaten (POST) gedacht sind
        $iconFile = $_FILES['group_icon'] ?? ($_FILES['avatar'] ?? null);
        if ($iconFile && $iconFile['error'] === 0) {
            $this->groupRepository->uploadImage($groupId, $iconFile);
        }

        if ($isUpdate) {
            if ($this->auth->getGroup() === $groupId) {
                $this->auth->refreshSessionPermissions($groupId);
            }

            return "Rechte für Gruppe '{$dto->groupName}' erfolgreich aktualisiert.";
        }

        return "Neue Gruppe '{$dto->groupName}' wurde erstellt.";
    }
}
