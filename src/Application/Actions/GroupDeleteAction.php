<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action zum Löschen einer Berechtigungsgruppe.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupDeleteAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Löscht eine Gruppe aus dem Berechtigungssystem. Schützt die Kern-Gruppe 'admin'.
     *
     * @param array<string, mixed> $post Datensatz mit group_id.
     *
     * @return string Ergebnisnachricht.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            $dto = SimpleIdentifierRequest::fromArray($post, 'group_id');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        if ($dto->identifier === 'admin') {
            return 'Fehler: Die Admin-Gruppe kann nicht gelöscht werden.';
        }

        $groups = $this->groupRepository->loadAll();
        if (isset($groups[$dto->identifier])) {
            unset($groups[$dto->identifier]);
            $this->groupRepository->saveAll($groups);

            $iconPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/group_images/' . $dto->identifier . '.webp';
            if (\file_exists($iconPath)) {
                @\unlink($iconPath);
            }

            return 'Gruppe gelöscht.';
        }

        return 'Fehler: Gruppe nicht gefunden.';
    }
}
