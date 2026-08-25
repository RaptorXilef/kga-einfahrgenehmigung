<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuthService;

#[Route('GET', '/users')]
#[RequiresAuth]
final readonly class UserManagementRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $this->renderer->render('admin_users', [
            'auth' => $this->auth,
            'roleRepository' => $this->roleRepository,
            'roles' => $this->roleRepository->loadAll(),
            'imageStorage' => $this->imageStorage,
            'permissions' => $this->config->get('permissions', []),
            'structure' => $this->config->get('structure', []),
            'userRepository' => $this->userRepository,
            'users' => $this->userRepository->loadAll(),
        ]);

        return null;
    }
}
