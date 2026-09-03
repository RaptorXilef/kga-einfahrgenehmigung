<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\UserSaveRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Entity\User;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;
use App\Core\Service\UserService;
use DomainException;

#[Route('POST', '/save_user')]
#[RequiresAuth]
final readonly class UserSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

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

            $users[$newId] = new User(
                $newId,
                $dto->username,
                $dto->group, // Das DTO liest den POST Key "group", übergibt ihn aber an Role
                \password_hash($dto->password, \PASSWORD_DEFAULT),
            );

            $this->userRepository->saveAll($users);

            if ($dto->avatar !== null) {
                $this->imageStorage->uploadImage('user_images', $newId, $dto->avatar);
            }

            $roles = $this->roleRepository->loadAll();
            $roleName = isset($roles[$dto->group]) ? $roles[$dto->group]->name : $dto->group;

            $this->auditLogger->log('USER_CREATE', "Neues Benutzerkonto '{$dto->username}' (ID: {$newId}, Rolle: {$roleName}) erstellt.");
            $this->sessionManager->addFlash('success', "Benutzer '{$dto->username}' erfolgreich erstellt.");

            return new RedirectResponse('users.php');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }
    }
}
