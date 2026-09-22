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
use App\Modules\Identity\Application\UseCases\ManageRoles\RenameRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageRoles\RenameRoleHandler;
use DomainException;
use Modules\System\Application\Services\AuditLoggerService;

#[Route('POST', '/rename_role')]
final readonly class RoleRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private AuditLoggerService $auditLogger,
        private RenameRoleHandler $renameHandler, // CQRS
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

            return new RedirectResponse('users');
        }

        try {
            $oldName = $this->renameHandler->handle(new RenameRoleCommand($dto->roleId, $dto->newRoleName));

            $this->auditLogger->log('ROLE_RENAME', "Rolle '{$oldName}' wurde umbenannt in '{$dto->newRoleName}'.");
            $this->sessionManager->addFlash('success', "Rolle wurde in '{$dto->newRoleName}' umbenannt.");

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
