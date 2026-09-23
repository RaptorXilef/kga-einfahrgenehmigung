<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\System\ImageStorageInterface;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;

#[Route('GET', '/profile')]
#[RequiresAuth]
final readonly class ProfileRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $userId = $this->auth->getUserId();

        if (\str_starts_with($userId, 'sys_')) {
            $this->sessionManager->addFlash('info', 'System-Accounts können nicht über das Frontend bearbeitet werden.');

            return new RedirectResponse('admin');
        }

        $roles = $this->roleRepository->loadAll();
        $user = $this->userRepository->findById($userId);

        $userRoleId = $user instanceof User ? $user->roleId : 'guest';
        $role = $roles[$userRoleId] ?? null;

        $html = $this->renderer->render('admin/profile', [
            'auth' => $this->auth,
            'role' => $role ? $role->name : $userRoleId,
            'roleRepository' => $this->roleRepository,
            'imageStorage' => $this->imageStorage,
            'userId' => $userId,
            'username' => $user instanceof User ? $user->username : 'Unbekannt',
            'userRepository' => $this->userRepository,
        ]);

        return new HtmlResponse($html);
    }
}
