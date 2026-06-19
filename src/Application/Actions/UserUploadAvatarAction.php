<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleUploadImageRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserUploadAvatarAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Verarbeitet den Upload und die Skalierung/Speicherung eines Benutzer-Profilbildes.
     *
     * @param array<string, mixed> $post Das Post-Array mit der Ziel-User-ID.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            // Kapselung des Uploads via DTO
            $dto = SimpleUploadImageRequest::fromRequest($post, 'user_id', $_FILES);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->userRepository->uploadImage($dto->identifier, $dto->file) ? 'Profilbild aktualisiert.' : 'Fehler beim Verarbeiten.';
    }
}
