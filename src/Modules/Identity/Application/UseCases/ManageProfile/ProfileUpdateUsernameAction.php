<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Application\UseCases\ManageUsers\RenameUserCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\RenameUserHandler;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;

#[Route('POST', '/change_own_username')]
final readonly class ProfileUpdateUsernameAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private SessionManager $sessionManager,
        private RenameUserHandler $renameHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $userId = $this->auth->getUserId();

        if (\str_starts_with($userId, 'sys_')) {
            $this->sessionManager->addFlash('error', 'System-Accounts können nicht bearbeitet werden.');

            return new RedirectResponse('admin');
        }

        try {
            $dto = ProfileUpdateUsernameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile');
        }

        try {
            $oldName = $this->renameHandler->handle(new RenameUserCommand($userId, $dto->newUsername));

            $this->sessionManager->updateAdminUsername($dto->newUsername);
            $this->auditLogger->log('PROFILE_USERNAME_CHANGE', "Eigenes Login/Anzeigename geändert (von '{$oldName}' zu '{$dto->newUsername}').");
            $this->sessionManager->addFlash('success', 'Erfolg: Ihr Anzeigename wurde aktualisiert.');

            return new RedirectResponse('profile');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile');
        }
    }
}
