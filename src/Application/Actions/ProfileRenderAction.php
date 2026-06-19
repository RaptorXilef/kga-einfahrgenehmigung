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
 * SPDX-License-Identifier: LicenseRef-Proprietary
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

    public function execute(array $requestData): mixed
    {
        $dto    = ViewRenderRequest::fromArray($requestData['get'] ?? []);
        $userId = $this->auth->getUserId();

        $users  = $this->userRepository->loadAll();
        $groups = $this->groupRepository->loadAll();

        $user        = $users[$userId] ?? null;
        $userGroupId = $user ? $user->groupId : 'guest';
        $group       = $groups[$userGroupId] ?? null;

        $this->renderer->render('profile', [
            'auth'            => $this->auth,
            'group'           => $group ? $group->name : $userGroupId,
            'groupRepository' => $this->groupRepository,
            'message'         => $dto->message,
            'userId'          => $userId,
            'username'        => $user ? $user->username : 'Unbekannt',
            'userRepository'  => $this->userRepository,
        ]);

        return null;
    }
}
