<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\RoleRenameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Core\Entity\Role;
use App\Core\Service\AuditLoggerService;

#[Route('POST', '/rename_role')]
final readonly class RoleRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private SessionManager $sessionManager,
        private AuditLoggerService $auditLogger,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.roles.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = RoleRenameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        $roles = $this->roleRepository->loadAll();
        if (!isset($roles[$dto->roleId])) {
            $this->sessionManager->addFlash('error', 'Fehler: Rolle nicht gefunden.');

            return new RedirectResponse('users.php');
        }

        $r = $roles[$dto->roleId];
        $oldName = $r->name;

        $roles[$dto->roleId] = new Role($r->id, $dto->newRoleName, $r->permissions);
        $this->roleRepository->saveAll($roles);

        $this->auditLogger->log('ROLE_RENAME', "Rolle '{$oldName}' wurde umbenannt in '{$dto->newRoleName}'.");
        $this->sessionManager->addFlash('success', "Rolle wurde in '{$dto->newRoleName}' umbenannt.");

        return new RedirectResponse('users.php');
    }
}
