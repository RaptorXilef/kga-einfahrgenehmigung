<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
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
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('upload_group_image')]
final readonly class GroupUploadImageAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.groups.manage';
    }

    /**
     * Verarbeitet den Upload eines Bildes für Gruppen-Icons.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleUploadImageRequest::fromRequest($request->post, 'group_id', $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        if ($this->imageStorage->uploadImage('group_images', $dto->identifier, $dto->file)) {
            $this->auditLogger->log('GROUP_ICON_UPLOAD', "Neues Icon für Gruppe '{$dto->identifier}' hochgeladen.");
            $this->sessionManager->addFlash('success', 'Gruppen-Icon aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler beim Verarbeiten des Bildes.');
        }

        return new RedirectResponse('users.php');
    }
}
