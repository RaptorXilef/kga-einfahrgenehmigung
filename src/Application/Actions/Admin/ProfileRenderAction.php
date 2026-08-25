<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuthService;

#[Route('GET', '/profile')]
#[RequiresAuth]
final readonly class ProfileRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $userId = $this->auth->getUserId();
        $users = $this->userRepository->loadAll();
        $roles = $this->roleRepository->loadAll();

        $user = $users[$userId] ?? null;
        $userRoleId = $user ? $user->roleId : 'guest';
        $role = $roles[$userRoleId] ?? null;

        $this->renderer->render('profile', [
            'auth' => $this->auth,
            'role' => $role ? $role->name : $userRoleId,
            'roleRepository' => $this->roleRepository,
            'imageStorage' => $this->imageStorage,
            'userId' => $userId,
            'username' => $user ? $user->username : 'Unbekannt',
            'userRepository' => $this->userRepository,
        ]);

        return null;
    }
}
