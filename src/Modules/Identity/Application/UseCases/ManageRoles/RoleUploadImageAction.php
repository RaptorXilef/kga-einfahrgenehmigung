<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\ImageStorageInterface;
use App\Modules\System\Application\Services\AuditLoggerService;

#[Route('GET', '/upload_role_image')]
#[Route('POST', '/upload_role_image')]
final readonly class RoleUploadImageAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.roles.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UploadRoleImageRequest::fromRequest($request->post, $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        if ($this->imageStorage->uploadImage('role', $dto->roleId, $dto->file)) {
            $this->auditLogger->log('ROLE_ICON_UPLOAD', "Neues Icon für Rolle '{$dto->roleId}' hochgeladen.");
            $this->sessionManager->addFlash('success', 'Rollen-Icon aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler beim Verarbeiten des Bildes.');
        }

        return new RedirectResponse('users');
    }
}
