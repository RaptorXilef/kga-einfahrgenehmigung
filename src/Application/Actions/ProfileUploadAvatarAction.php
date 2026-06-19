<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUploadAvatarRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ProfileUploadAvatarAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $post): mixed
    {
        try {
            // Keine ungeschützte $_FILES-Abfrage mehr!
            $dto = ProfileUploadAvatarRequest::fromFiles($_FILES);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $userId = $_SESSION['user_id'] ?? '';
        if ($this->userRepository->uploadImage($userId, $dto->file)) {
            return 'Erfolg: Profilbild wurde aktualisiert.';
        }

        return 'Fehler bei der Bildverarbeitung.';
    }
}
