<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AuditLoggerInterface;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserPasswordCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserPasswordHandler;
use DomainException;
use Override;

#[Route('POST', '/change_own_password')]
final readonly class ProfileUpdatePasswordAction implements ActionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private SessionManager $sessionManager,
        private AuditLoggerInterface $auditLogger,
        private ChangeUserPasswordHandler $changePasswordHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $userId = $this->auth->getUserId();

        if (\str_starts_with($userId, 'sys_')) {
            $this->sessionManager->addFlash('error', 'System-Accounts können nicht bearbeitet werden.');

            return new RedirectResponse('admin');
        }

        try {
            $dto = ProfileUpdatePasswordRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile');
        }

        try {
            $newHash = $this->changePasswordHandler->handle(
                new ChangeUserPasswordCommand($userId, $dto->newPassword, $dto->oldPassword),
            );

            $this->sessionManager->setAuthSession($userId, $this->auth->getRole(), $this->auth->getUsername(), $newHash);
            $this->auditLogger->log('PROFILE_PASSWORD_CHANGE', 'Eigenes Kennwort wurde geändert.');
            $this->sessionManager->addFlash('success', 'Erfolg: Ihr Passwort wurde geändert.');

            return new RedirectResponse('profile');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile');
        }
    }
}
