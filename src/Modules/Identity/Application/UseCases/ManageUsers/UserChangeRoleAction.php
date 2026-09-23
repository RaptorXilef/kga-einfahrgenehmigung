<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;

#[Route('POST', '/change_user_role')]
#[RequiresAuth]
final readonly class UserChangeRoleAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private RoleRepositoryInterface $roleRepository,
        private UserRepositoryInterface $userRepository,
        private SessionManager $sessionManager,
        private ChangeUserRoleHandler $changeRoleHandler,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ChangeUserRoleRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            $user = $this->userRepository->findById($dto->userId);
            if (!$user instanceof User) {
                throw new DomainException('Fehler: Benutzer nicht gefunden.');
            }

            $oldRole = $user->roleId;
            $username = $user->username;

            $this->changeRoleHandler->handle(new ChangeUserRoleCommand($dto->userId, $dto->roleId));

            $roles = $this->roleRepository->loadAll();
            $oldRoleName = isset($roles[$oldRole]) ? $roles[$oldRole]->name : $oldRole;
            $newRoleName = isset($roles[$dto->roleId]) ? $roles[$dto->roleId]->name : $dto->roleId;

            $this->auditLogger->log(
                'USER_CHANGE_ROLE',
                "Rolle von Benutzer '{$username}' (ID: {$dto->userId}) geändert: Von '{$oldRoleName}' auf '{$newRoleName}'.",
            );

            $this->sessionManager->addFlash('success', "Rolle für '{$username}' geändert.");

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
