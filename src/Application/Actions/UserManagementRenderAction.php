<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ViewRenderRequest;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserManagementRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = ViewRenderRequest::fromArray($request->get);

        $this->renderer->render('admin_users', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'groups'          => $this->groupRepository->loadAll(),
            'message'         => $dto->message,
            'permissions'     => $this->config->get('permissions', []),
            'structure'       => $this->config->get('structure', []),
            'userRepository'  => $this->userRepository,
            'users'           => $this->userRepository->loadAll(),
        ]);

        return null;
    }
}
