<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupService
{
    public function __construct(
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function deleteGroup(string $groupId): void
    {
        if ($groupId === 'admin') {
            throw new \DomainException('Fehler: Die Admin-Gruppe kann nicht gelöscht werden.');
        }

        $groups = $this->groupRepository->loadAll();
        if (! isset($groups[$groupId])) {
            throw new \DomainException('Fehler: Gruppe nicht gefunden.');
        }

        unset($groups[$groupId]);
        $this->groupRepository->saveAll($groups);

        $iconPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/group_images/' . $groupId . '.webp';
        if (\file_exists($iconPath)) {
            @\unlink($iconPath);
        }
    }
}
