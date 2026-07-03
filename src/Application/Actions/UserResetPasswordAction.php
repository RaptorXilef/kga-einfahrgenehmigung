<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\UserResetPasswordRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('change_user_password')]
final readonly class UserResetPasswordAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
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

            return new RedirectResponse('users.php');
        }

        $users = $this->userRepository->loadAll();

        if (isset($users[$dto->userId])) {
            $u                   = $users[$dto->userId];
            $users[$dto->userId] = new User($u->id, $u->username, $u->groupId, \password_hash($dto->newPassword, \PASSWORD_DEFAULT));
            $this->userRepository->saveAll($users);

            $this->sessionManager->addFlash('success', 'Passwort wurde zurückgesetzt.');

            return new RedirectResponse('users.php');
        }

        $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

        return new RedirectResponse('users.php');
    }
}
