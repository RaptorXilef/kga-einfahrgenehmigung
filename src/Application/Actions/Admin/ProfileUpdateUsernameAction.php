<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\DTO\ProfileUpdateUsernameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;
use App\Core\Service\UserService;
use DomainException;

#[Route('POST', '/change_own_username')]
final readonly class ProfileUpdateUsernameAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
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
            $this->userService->ensureUsernameIsUnique($dto->newUsername, $userId);
            $users = $this->userRepository->loadAll();

            if (isset($users[$userId])) {
                $u = $users[$userId];
                $oldName = $u->username;
                // FIX: u->roleId statt u->groupId
                $users[$userId] = new User($u->id, $dto->newUsername, $u->roleId, $u->passwordHash);
                $this->userRepository->saveAll($users);

                $this->sessionManager->updateAdminUsername($dto->newUsername);
                $this->auditLogger->log('PROFILE_USERNAME_CHANGE', "Eigenes Login/Anzeigename geändert (von '{$oldName}' zu '{$dto->newUsername}').");
                $this->sessionManager->addFlash('success', 'Erfolg: Ihr Anzeigename wurde aktualisiert.');

                return new RedirectResponse('profile');
            }

            $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

            return new RedirectResponse('profile');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile');
        }
    }
}
