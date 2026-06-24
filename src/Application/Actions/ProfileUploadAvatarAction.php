<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUploadAvatarRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Storage\ImageStorageService;

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
            $dto = ProfileUploadAvatarRequest::fromFiles($request->files);
        } catch (ValidationException $e) {
            return new RedirectResponse('profile.php?msg=' . \urlencode($e->getMessage()));
        }
        $userId = $this->auth->getUserId();
        if ($this->imageStorage->uploadImage('user_images', $userId, $dto->file)) {
            return new RedirectResponse('profile.php?msg=' . \urlencode('Erfolg: Profilbild wurde aktualisiert.'));
        }

        return new RedirectResponse('profile.php?msg=' . \urlencode('Fehler bei der Bildverarbeitung.'));
    }
}
