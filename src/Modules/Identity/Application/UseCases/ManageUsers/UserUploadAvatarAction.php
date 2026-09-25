<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use App\Contracts\System\ImageStorageInterface;
use Override;

#[Route('POST', '/upload_avatar')]
#[RequiresAuth]
final readonly class UserUploadAvatarAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = UploadUserAvatarRequest::fromRequest($request->post, $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        if ($this->imageStorage->uploadImage('user', $dto->userId, $dto->file)) {
            $this->auditLogger->log('USER_AVATAR_UPLOAD', "Neues Profilbild für Benutzer (ID: {$dto->userId}) hochgeladen.");
            $this->sessionManager->addFlash('success', 'Profilbild aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler beim Verarbeiten.');
        }

        return new RedirectResponse('users');
    }
}
