<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\UseCases\ManageRoles\DeleteRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageRoles\DeleteRoleHandler;
use DomainException;
use Modules\System\Application\Services\AuditLoggerService;

#[Route('POST', '/delete_role')]
#[RequiresAuth]
final readonly class RoleDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private DeleteRoleHandler $deleteHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.roles.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'group_id');
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            $roleName = $this->deleteHandler->handle(new DeleteRoleCommand($dto->identifier));

            $this->auditLogger->log('ROLE_DELETE', "Rechte-Rolle '{$roleName}' (ID: {$dto->identifier}) wurde gelöscht.");
            $this->sessionManager->addFlash('success', 'Rolle gelöscht. (Zugeordnete Benutzer fallen auf Standard-Rechte zurück).');

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
