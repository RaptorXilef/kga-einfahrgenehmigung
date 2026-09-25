<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use DomainException;
use Override;

#[Route('POST', '/delete_role')]
#[RequiresAuth]
final readonly class RoleDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private DeleteRoleHandler $deleteHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.roles.manage';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = DeleteRoleRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            $roleName = $this->deleteHandler->handle(new DeleteRoleCommand($dto->roleId));

            $this->auditLogger->log('ROLE_DELETE', "Rechte-Rolle '{$roleName}' (ID: {$dto->roleId}) wurde gelöscht.");
            $this->sessionManager->addFlash('success', 'Rolle gelöscht. (Zugeordnete Benutzer fallen auf Standard-Rechte zurück).');

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
