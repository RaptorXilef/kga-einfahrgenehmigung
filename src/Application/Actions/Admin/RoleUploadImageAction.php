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

/**
 * TODO DOCBLOCK
 */
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
        return 'system.permissions.roles.manage';
    }

    /**
     * Verarbeitet den Upload eines Bildes für Gruppen-Icons.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            // Aus Kompatibilität zum HTML lesen wir weiterhin 'group_id'
            $dto = SimpleUploadImageRequest::fromRequest($request->post, 'group_id', $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        if ($this->imageStorage->uploadImage('role_images', $dto->identifier, $dto->file)) {
            $this->auditLogger->log('ROLE_ICON_UPLOAD', "Neues Icon für Rolle '{$dto->identifier}' hochgeladen.");
            $this->sessionManager->addFlash('success', 'Rollen-Icon aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler beim Verarbeiten des Bildes.');
        }

        return new RedirectResponse('users.php');
    }
}
