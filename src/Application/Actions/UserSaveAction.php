<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserSaveRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;
use App\Core\Service\ImageStorageService;

/**
 * Action zum Erstellen eines neuen Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserSaveAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private UserRepositoryInterface $userRepository,
        private ImageStorageService $imageStorage,
    ) {
    }

    /**
     * Erstellt einen neuen Datensatz in der Benutzerverwaltung inklusive Passwort-Hashing.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserSaveRequest::fromArray($request->post, $request->files);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $users = $this->userRepository->loadAll();
        foreach ($users as $userEntity) {
            if (\strtolower(\trim((string) $userEntity->username)) === \strtolower($dto->username)) {
                return "Fehler: Ein Benutzer mit dem Namen '{$dto->username}' existiert bereits im System.";
            }
        }
        do {
            $newId = $this->auth->generateId('usr_');
        } while (isset($users[$newId]));

        $users[$newId] = new User(
            $newId,
            $dto->username,
            $dto->group,
            \password_hash($dto->password, \PASSWORD_DEFAULT),
        );
        $this->userRepository->saveAll($users);

        if ($dto->avatar !== null) {
            $this->imageStorage->uploadImage('user_images', $newId, $dto->avatar);
        }

        return "Benutzer '{$dto->username}' erfolgreich erstellt.";
    }
}
