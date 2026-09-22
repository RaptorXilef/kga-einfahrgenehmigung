<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Security\Sanitizer;
use App\Core\Service\AuditLoggerService;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserRoleHandler;
use DomainException;

#[Route('POST', '/change_user_role')]
#[RequiresAuth]
final readonly class UserChangeRoleAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private RoleRepositoryInterface $roleRepository, // Read-Repo
        private UserRepositoryInterface $userRepository, // Read-Repo
        private SessionManager $sessionManager,
        private ChangeUserRoleHandler $changeRoleHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        $userId = Sanitizer::string($request->post['user_id'] ?? '');
        // TODO später zu role ändern
        $roleId = Sanitizer::string($request->post['group'] ?? ''); // Behält aus UI-Gründen den Post-Key "group"

        if ($userId === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Kein Benutzer ausgewählt.');

            return new RedirectResponse('users');
        }

        if ($roleId === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Rolle ausgewählt.');

            return new RedirectResponse('users');
        }

        try {
            $users = $this->userRepository->loadAll();
            if (!isset($users[$userId])) {
                throw new DomainException('Fehler: Benutzer nicht gefunden.');
            }
            $oldRole = $users[$userId]->roleId;
            $username = $users[$userId]->username;

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
