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
use App\Core\Entity\User;
use App\Core\Security\Sanitizer;
use App\Core\Service\AuditLoggerService;

#[Route('POST', '/change_user_role')]
#[RequiresAuth]
final readonly class UserChangeRoleAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private RoleRepositoryInterface $roleRepository,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        $userId = Sanitizer::string($request->post['user_id'] ?? '');
        $roleId = Sanitizer::string($request->post['group'] ?? ''); // Behält aus UI-Gründen den Post-Key "group"

        if ($userId === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Kein Benutzer ausgewählt.');

            return new RedirectResponse('users');
        }

        if ($roleId === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Rolle ausgewählt.');

            return new RedirectResponse('users');
        }

        $users = $this->userRepository->loadAll();
        if (isset($users[$userId])) {
            $u = $users[$userId];
            $oldRole = $u->roleId;

            // User Entity via Konstruktor neu aufbauen
            $users[$userId] = new User(
                $u->id,
                $u->username,
                $roleId,
                $u->passwordHash,
            );
            $this->userRepository->saveAll($users);

            $roles = $this->roleRepository->loadAll();
            $oldRoleName = isset($roles[$oldRole]) ? $roles[$oldRole]->name : $oldRole;
            $newRoleName = isset($roles[$roleId]) ? $roles[$roleId]->name : $roleId;

            $this->auditLogger->log(
                'USER_CHANGE_ROLE',
                "Rolle von Benutzer '{$u->username}' (ID: {$u->id}) geändert: Von '{$oldRoleName}' auf '{$newRoleName}'.",
            );

            $this->sessionManager->addFlash('success', "Rolle für '{$u->username}' geändert.");

            return new RedirectResponse('users');
        }

        $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

        return new RedirectResponse('users');
    }
}
