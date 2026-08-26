<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuditLoggerService;

#[Route('GET', '/upload_avatar')]
#[Route('POST', '/upload_avatar')]
final readonly class UserUploadAvatarAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleUploadImageRequest::fromRequest($request->post, 'user_id', $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        if ($this->imageStorage->uploadImage('user_images', $dto->identifier, $dto->file)) {
            $this->auditLogger->log('USER_AVATAR_UPLOAD', "Neues Profilbild für Benutzer (ID: {$dto->identifier}) hochgeladen.");
            $this->sessionManager->addFlash('success', 'Profilbild aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler beim Verarbeiten.');
        }

        return new RedirectResponse('users.php');
    }
}
