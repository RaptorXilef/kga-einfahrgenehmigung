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
use App\Contracts\System\ImageStorageInterface;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use DomainException;
use Override;

#[Route('POST', '/save_user')]
#[RequiresAuth]
final readonly class UserSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private CreateUserHandler $createHandler,
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
            $dto = UserSaveRequest::fromArray($request->post, $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            $newId = $this->createHandler->handle(new CreateUserCommand(
                $dto->username,
                $dto->password,
                $dto->group,
            ));

            if ($dto->avatar !== null) {
                $this->imageStorage->uploadImage('user', $newId, $dto->avatar);
            }

            $roles = $this->roleRepository->loadAll();
            $roleName = isset($roles[$dto->group]) ? $roles[$dto->group]->name : $dto->group;

            $this->auditLogger->log('USER_CREATE', "Neues Benutzerkonto '{$dto->username}' (ID: {$newId}, Rolle: {$roleName}) erstellt.");
            $this->sessionManager->addFlash('success', "Benutzer '{$dto->username}' erfolgreich erstellt.");

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
