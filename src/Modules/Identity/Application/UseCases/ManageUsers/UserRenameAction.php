<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

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

#[Route('POST', '/rename_user')]
#[RequiresAuth]
final readonly class UserRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private RenameUserHandler $renameHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = UserRenameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
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
