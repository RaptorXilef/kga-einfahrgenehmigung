<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserResetPasswordRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserResetPasswordAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Setzt das Passwort eines Benutzers administrativ (ohne Alt-Passwort-Prüfung) zurück.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserResetPasswordRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $users = $this->userRepository->loadAll();
        if (isset($users[$dto->userId])) {
            $u                   = $users[$dto->userId];
            $users[$dto->userId] = new User($u->id, $u->username, $u->groupId, \password_hash($dto->newPassword, \PASSWORD_DEFAULT));
            $this->userRepository->saveAll($users);

            return 'Passwort wurde zurückgesetzt.';
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
