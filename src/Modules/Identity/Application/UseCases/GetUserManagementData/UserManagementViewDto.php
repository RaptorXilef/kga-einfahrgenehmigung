<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\GetUserManagementData;

final readonly class UserManagementViewDto
{
    public function __construct(
        /**
         * @var UserListDto[]
         */
        public array $users,
        /**
         * @var RoleListDto[]
         */
        public array $roles,
        public array $globalRoleOptions,
        public int $userCount,
    ) {
    }
}
