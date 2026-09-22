<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use App\SharedKernel\Application\Security\Sanitizer;
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
        $userId = Sanitizer::string($request->post['user_id'] ?? '');
        $roleId = Sanitizer::string($request->post['group'] ?? '');

        if ($userId === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Kein Benutzer ausgewählt.');

            return new RedirectResponse('users');
        }

        if ($roleId === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Rolle ausgewählt.');

            return new RedirectResponse('users');
        }

        try {
            $user = $this->userRepository->findById($userId);
            if ($user === null) {
                throw new DomainException('Fehler: Benutzer nicht gefunden.');
            }

            $oldRole = $user->roleId;
            $username = $user->username;

            $this->changeRoleHandler->handle(new ChangeUserRoleCommand($userId, $roleId));

            $roles = $this->roleRepository->loadAll();
            $oldRoleName = isset($roles[$oldRole]) ? $roles[$oldRole]->name : $oldRole;
            $newRoleName = isset($roles[$roleId]) ? $roles[$roleId]->name : $roleId;

            $this->auditLogger->log(
                'USER_CHANGE_ROLE',
                "Rolle von Benutzer '{$username}' (ID: {$userId}) geändert: Von '{$oldRoleName}' auf '{$newRoleName}'.",
            );

            $this->sessionManager->addFlash('success', "Rolle für '{$username}' geändert.");

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
