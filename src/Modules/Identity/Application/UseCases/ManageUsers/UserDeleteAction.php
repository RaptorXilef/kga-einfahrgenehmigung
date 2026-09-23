<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;

#[Route('GET', '/delete_user')]
#[Route('POST', '/delete_user')]
final readonly class UserDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private DeleteUserHandler $deleteHandler,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    public function execute(ServerRequest $request): mixed
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

            $avatarPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/user/' . $dto->userId . '.webp';
            if (\file_exists($avatarPath)) {
                @\unlink($avatarPath);
            }

            $this->auditLogger->log('USER_DELETE', "Benutzerkonto '{$deletedName}' (ID: {$dto->userId}) unwiderruflich gelöscht.");
            $this->sessionManager->addFlash('success', "Benutzer '{$deletedName}' wurde entfernt.");

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
