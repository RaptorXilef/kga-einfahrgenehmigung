<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUploadAvatarRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\ImageStorageService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ProfileUploadAvatarAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ImageStorageService $imageStorage,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            // Keine ungeschützte $request->files-Abfrage mehr!
            $dto = ProfileUploadAvatarRequest::fromFiles($request->files);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $userId = $this->auth->getUserId();
        if ($this->imageStorage->uploadImage('user_images', $userId, $dto->file)) {
            return 'Erfolg: Profilbild wurde aktualisiert.';
        }

        return 'Fehler bei der Bildverarbeitung.';
    }
}
