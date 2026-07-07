<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuditLoggerService; // <--- NEU
use App\Core\Service\AuthService;
use App\Core\Service\UserService;

/**
 * Action zum Löschen eines Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('delete_user')]
final readonly class UserDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger, // <--- NEU
        private AuthService $auth,
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        try {
            $this->userService->ensureNoSelfExclusion($dto->identifier, $this->auth->getUserId());
            $users = $this->userRepository->loadAll();

            if (isset($users[$dto->identifier])) {
                $name = $users[$dto->identifier]->username;
                unset($users[$dto->identifier]);
                $this->userRepository->saveAll($users);

                $avatarPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/user_images/' . $dto->identifier . '.webp';
                if (\file_exists($avatarPath)) {
                    @\unlink($avatarPath);
                }

                // LOG SCHREIBEN
                $this->auditLogger->log('USER_DELETE', "Benutzerkonto '{$name}' (ID: {$dto->identifier}) unwiderruflich gelöscht.");

                $this->sessionManager->addFlash('success', "Benutzer '$name' wurde entfernt.");

                return new RedirectResponse('users.php');
            }

            $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

            return new RedirectResponse('users.php');
        } catch (\DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }
    }
}
