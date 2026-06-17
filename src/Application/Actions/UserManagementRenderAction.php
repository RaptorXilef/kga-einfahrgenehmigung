<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/UserManagementRenderAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $get = $requestData['get'];
        $this->renderer->render('admin_users', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'groups'          => $this->groupRepository->loadAll(),
            'message'         => (string) ($get['msg'] ?? ''),
            'permissions'     => $this->config->get('permissions', []),
            'structure'       => $this->config->get('structure', []),
            'userRepository'  => $this->userRepository,
            'users'           => $this->userRepository->loadAll(),
        ]);
    }
}
