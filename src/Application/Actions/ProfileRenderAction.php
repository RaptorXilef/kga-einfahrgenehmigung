<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ViewRenderRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/ProfileRenderAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ProfileRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $dto = ViewRenderRequest::fromArray($requestData['get'] ?? []);

        $userId      = $_SESSION['user_id'] ?? '';
        $users       = $this->userRepository->loadAll();
        $groups      = $this->groupRepository->loadAll();
        $userGroupId = $users[$userId]['group'] ?? 'guest';

        $this->renderer->render('profile', [
            'auth'            => $this->auth,
            'group'           => $groups[$userGroupId]['name'] ?? $userGroupId,
            'groupRepository' => $this->groupRepository,
            'message'         => $dto->message,
            'userId'          => $userId,
            'username'        => $users[$userId]['username'] ?? 'Unbekannt',
            'userRepository'  => $this->userRepository,
        ]);
    }
}
