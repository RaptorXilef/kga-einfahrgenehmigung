<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\UserResetPasswordRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\UserRepositoryInterface; // Legacy für Namensauflösung
use App\Core\Service\AuditLoggerService;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserPasswordCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserPasswordHandler;
use DomainException;

#[Route('POST', '/change_user_password')]
final readonly class UserResetPasswordAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $legacyUserRepository, // Für Logging Info
        private ChangeUserPasswordHandler $changePasswordHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.users.manage';
    }

    /**
     * Setzt das Passwort eines Benutzers administrativ (ohne Alt-Passwort-Prüfung) zurück.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserResetPasswordRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }

        try {
            // Name für das Log aus dem alten Read-Repository laden
            $users = $this->legacyUserRepository->loadAll();
            $username = isset($users[$dto->userId]) ? $users[$dto->userId]->username : 'Unbekannt';

            $this->changePasswordHandler->handle(new ChangeUserPasswordCommand($dto->userId, $dto->newPassword));

            $this->auditLogger->log('USER_RESET_PASSWORD', "Kennwort für Benutzer '{$username}' (ID: {$dto->userId}) manuell zurückgesetzt.");
            $this->sessionManager->addFlash('success', 'Passwort wurde zurückgesetzt.');

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
