<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserUploadAvatarAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Verarbeitet den Upload und die Skalierung/Speicherung eines Benutzer-Profilbildes.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            // Kapselung des Uploads via DTO
            $dto = SimpleUploadImageRequest::fromRequest($request->post, 'user_id', $request->files);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->userRepository->uploadImage($dto->identifier, $dto->file) ? 'Profilbild aktualisiert.' : 'Fehler beim Verarbeiten.';
    }
}
