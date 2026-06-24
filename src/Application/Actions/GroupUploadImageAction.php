<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Storage\ImageStorageService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupUploadImageAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ImageStorageService $imageStorage,
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
            return new RedirectResponse('users.php?msg=' . \urlencode($e->getMessage()));
        }
        $msg = $this->imageStorage->uploadImage('group_images', $dto->identifier, $dto->file) ? 'Gruppen-Icon aktualisiert.' : 'Fehler beim Verarbeiten.';

        return new RedirectResponse('users.php?msg=' . \urlencode($msg));
    }
}
