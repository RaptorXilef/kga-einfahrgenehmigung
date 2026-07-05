<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\UserSaveRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Entity\User;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;
use App\Core\Service\UserService;

/**
 * Action zum Erstellen eines neuen Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('save_user')]
final readonly class UserSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository, // <-- NEU: Für Gruppen-Namen
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
    }

    /**
     * Erstellt einen neuen Datensatz in der Benutzerverwaltung inklusive Passwort-Hashing.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserSaveRequest::fromArray($request->post, $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        try {
            $this->userService->ensureUsernameIsUnique($dto->username);
            $users = $this->userRepository->loadAll();

            do {
                $newId = $this->auth->generateId('usr_');
            } while (isset($users[$newId]));

            $users[$newId] = new User($newId, $dto->username, $dto->group, \password_hash($dto->password, \PASSWORD_DEFAULT));
            $this->userRepository->saveAll($users);

            if ($dto->avatar !== null) {
                $this->imageStorage->uploadImage('user_images', $newId, $dto->avatar);
            }

            // Gruppen-Namen ermitteln
            $groups    = $this->groupRepository->loadAll();
            $groupName = isset($groups[$dto->group]) ? $groups[$dto->group]->name : $dto->group;

            // LOG SCHREIBEN
            $this->auditLogger->log('USER_CREATE', "Neues Benutzerkonto '{$dto->username}' (ID: {$newId}, Gruppe: {$groupName}) erstellt.");

            $this->sessionManager->addFlash('success', "Benutzer '{$dto->username}' erfolgreich erstellt.");

            return new RedirectResponse('users.php');
        } catch (\DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }
    }
}
