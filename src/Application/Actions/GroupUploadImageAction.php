<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            // Vollständige Kapselung von ID und Datei im DTO
            $dto = SimpleUploadImageRequest::fromRequest($post, 'group_id', $_FILES);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->groupRepository->uploadImage($dto->identifier, $dto->file) ? 'Gruppen-Icon aktualisiert.' : 'Fehler beim Verarbeiten.';
    }
}
