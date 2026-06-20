<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\ViewActionInterface;

/**
 * TODO DOCBLOCK
 * Factory zur dynamischen Auflösung von User- und Profil-Routen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserActionFactory
{
    public function __construct(
        private Container $container,
    ) {
    }

    public function create(string $actionKey): ActionInterface|ViewActionInterface|null
    {
        $class = match ($actionKey) {
            'change_user_group'    => UserChangeGroupAction::class,
            'change_user_password' => UserResetPasswordAction::class,
            'delete_user'          => UserDeleteAction::class,
            'rename_user'          => UserRenameAction::class,
            'save_user'            => UserSaveAction::class,
            'upload_avatar'        => UserUploadAvatarAction::class,
            'delete_group'         => GroupDeleteAction::class,
            'rename_group'         => GroupRenameAction::class,
            'save_group'           => GroupSaveAction::class,
            'upload_group_image'   => GroupUploadImageAction::class,
            'change_own_avatar'    => ProfileUploadAvatarAction::class,
            'change_own_password'  => ProfileUpdatePasswordAction::class,
            'change_own_username'  => ProfileUpdateUsernameAction::class,
            'render_users'         => UserManagementRenderAction::class,
            'render_profile'       => ProfileRenderAction::class,
            default                => null,
        };

        return $class ? $this->container->get($class) : null;
    }
}
