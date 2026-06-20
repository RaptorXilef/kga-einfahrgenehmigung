<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupUploadImageAction implements ActionInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Verarbeitet den Upload eines Bildes für Gruppen-Icons.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            // Vollständige Kapselung von ID und Datei im DTO
            $dto = SimpleUploadImageRequest::fromRequest($request->post, 'group_id', $request->files);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->groupRepository->uploadImage($dto->identifier, $dto->file) ? 'Gruppen-Icon aktualisiert.' : 'Fehler beim Verarbeiten.';
    }
}
