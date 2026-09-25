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

#[Route('POST', '/change_user_password')]
#[RequiresAuth]
final readonly class UserResetPasswordAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private ChangeUserPasswordHandler $changePasswordHandler,
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
            $dto = UserResetPasswordRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            $result = $this->changePasswordHandler->handle(new ChangeUserPasswordCommand($dto->userId, $dto->newPassword));

            $this->auditLogger->log('USER_RESET_PASSWORD', "Kennwort für Benutzer '{$result->username}' (ID: {$dto->userId}) manuell zurückgesetzt.");
            $this->sessionManager->addFlash('success', 'Passwort wurde zurückgesetzt.');

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
