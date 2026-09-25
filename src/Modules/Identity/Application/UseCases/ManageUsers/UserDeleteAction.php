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
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AuditLoggerInterface;
use App\Contracts\System\ImageStorageInterface;
use DomainException;
use Override;

#[Route('POST', '/delete_user')]
#[RequiresAuth]
final readonly class UserDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private AuthorizationInterface $auth,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private DeleteUserHandler $deleteHandler,
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
            $dto = DeleteUserRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            $deletedName = $this->deleteHandler->handle(new DeleteUserCommand(
                $dto->userId,
                $this->auth->getUserId(),
            ));

            $this->imageStorage->deleteImage('user', $dto->userId);

            $this->auditLogger->log('USER_DELETE', "Benutzerkonto '{$deletedName}' (ID: {$dto->userId}) unwiderruflich gelöscht.");
            $this->sessionManager->addFlash('success', "Benutzer '{$deletedName}' wurde entfernt.");

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
