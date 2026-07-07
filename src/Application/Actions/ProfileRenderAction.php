<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('render_profile')]
final readonly class ProfileRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private ImageStorageInterface $imageStorage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
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
            'imageStorage'    => $this->imageStorage,
            'userId'          => $userId,
            'username'        => $user ? $user->username : 'Unbekannt',
            'userRepository'  => $this->userRepository,
        ]);

        return null;
    }
}
