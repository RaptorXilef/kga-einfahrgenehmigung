<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\ProfileUploadAvatarRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('change_own_avatar')]
final readonly class ProfileUploadAvatarAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private AuditLoggerService $auditLogger,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ProfileUploadAvatarRequest::fromFiles($request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile.php');
        }

        $userId = $this->auth->getUserId();

        if ($this->imageStorage->uploadImage('user_images', $userId, $dto->file)) {
            $this->auditLogger->log('PROFILE_AVATAR_UPLOAD', 'Eigenes Profilbild aktualisiert.');
            $this->sessionManager->addFlash('success', 'Erfolg: Profilbild wurde aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler bei der Bildverarbeitung.');
        }

        return new RedirectResponse('profile.php');
    }
}
