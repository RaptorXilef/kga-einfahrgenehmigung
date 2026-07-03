<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Storage\ImageStorageService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('upload_avatar')]
final readonly class UserUploadAvatarAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ImageStorageService $imageStorage,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
    }

    /**
     * Verarbeitet den Upload und die Skalierung/Speicherung eines Benutzer-Profilbildes.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleUploadImageRequest::fromRequest($request->post, 'user_id', $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        if ($this->imageStorage->uploadImage('user_images', $dto->identifier, $dto->file)) {
            $this->sessionManager->addFlash('success', 'Profilbild aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler beim Verarbeiten.');
        }

        return new RedirectResponse('users.php');
    }
}
