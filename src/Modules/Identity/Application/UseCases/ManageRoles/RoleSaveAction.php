<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\System\Application\Services\AuditLoggerService;

#[Route('POST', '/save_role')]
#[RequiresAuth]
final readonly class RoleSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private SessionManager $sessionManager,
        private SaveRoleHandler $saveRoleHandler,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.roles.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = RoleSaveRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        $result = $this->saveRoleHandler->handle(new SaveRoleCommand(
            $dto->roleId,
            $dto->roleName,
            $dto->inheritRole,
            $dto->permissions,
        ));

        if ($result->isUpdate) {
            if ($this->auth->getRole() === $result->roleId) {
                $this->auth->refreshSessionPermissions($result->roleId);
            }
            $this->auditLogger->log('ROLE_UPDATE', "Rechte-Matrix für Rolle '{$dto->roleName}' (ID: {$result->roleId}) aktualisiert.");
            $this->sessionManager->addFlash('success', "Rechte für Rolle '{$dto->roleName}' erfolgreich aktualisiert.");
        } else {
            $this->auditLogger->log('ROLE_CREATE', "Neue Rechte-Rolle '{$dto->roleName}' (ID: {$result->roleId}) erstellt.");
            $this->sessionManager->addFlash('success', "Neue Rolle '{$dto->roleName}' wurde erstellt.");
        }

        return new RedirectResponse('users');
    }
}
