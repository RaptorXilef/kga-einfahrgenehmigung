<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;

/**
 * Action zum Löschen einer Berechtigungsgruppe.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.groups.manage';
    }

    /**
     * Löscht eine Gruppe aus dem Berechtigungssystem. Schützt die Kern-Gruppe 'admin'.
     *
     * @return string Ergebnisnachricht.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'group_id');
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
