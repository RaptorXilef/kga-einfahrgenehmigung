<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Storage\ImageStorageService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('render_users')]
final readonly class UserManagementRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private ImageStorageService $imageStorage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $this->renderer->render('admin_users', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'groups'          => $this->groupRepository->loadAll(),
            'imageStorage'    => $this->imageStorage,
            'permissions'     => $this->config->get('permissions', []),
            'structure'       => $this->config->get('structure', []),
            'userRepository'  => $this->userRepository,
            'users'           => $this->userRepository->loadAll(),
        ]);

        return null;
    }
}
