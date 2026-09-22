<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\UserSaveRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuditLoggerService;
use App\Modules\Identity\Application\UseCases\ManageUsers\CreateUserCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\CreateUserHandler;
use DomainException;

#[Route('POST', '/save_user')]
#[RequiresAuth]
final readonly class UserSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private RoleRepositoryInterface $roleRepository, // Legacy-Repo für Namensauflösung im Log
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private CreateUserHandler $createHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserSaveRequest::fromArray($request->post, $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            // Handler liefert die ID zurück, damit wir das Bild richtig speichern können
            $newId = $this->createHandler->handle(new CreateUserCommand(
                $dto->username,
                $dto->password,
                $dto->group,
            ));

            if ($dto->avatar !== null) {
                // BUGFIX: War ehemals auf den falschen Order 'user_images' gemappt
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
