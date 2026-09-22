<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\UserRenameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\UseCases\ManageUsers\RenameUserCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\RenameUserHandler;
use DomainException;
use Modules\System\Application\Services\AuditLoggerService;

#[Route('POST', '/rename_user')]
final readonly class UserRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private RenameUserHandler $renameHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    /**
     * Benennt den Login-Namen eines existierenden Benutzers um.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserRenameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            // Handler liefert den alten Namen für das Log
            $oldName = $this->renameHandler->handle(new RenameUserCommand($dto->userId, $dto->newUsername));

            $this->auditLogger->log('USER_RENAME', "Benutzer-Anzeigename von '{$oldName}' in '{$dto->newUsername}' (ID: {$dto->userId}) geändert.");
            $this->sessionManager->addFlash('success', 'Login-Name aktualisiert.');

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
