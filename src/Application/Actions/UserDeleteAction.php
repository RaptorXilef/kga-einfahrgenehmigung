<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action zum Löschen eines Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
    }

    /**
     * Löscht einen Benutzer aus dem System. Verhindert den Selbstausschluss des aktiven Admins.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'user_id');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        if ($dto->identifier === $this->auth->getUserId()) {
            return 'Fehler: Selbstausschluss nicht möglich.';
        }
        $users = $this->userRepository->loadAll();
        if (isset($users[$dto->identifier])) {
            $name = $users[$dto->identifier]->username;
            unset($users[$dto->identifier]);
            $this->userRepository->saveAll($users);
            $avatarPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/user_images/' . $dto->identifier . '.webp';
            if (\file_exists($avatarPath)) {
                @\unlink($avatarPath);
            }

            return "Benutzer '$name' wurde entfernt.";
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
