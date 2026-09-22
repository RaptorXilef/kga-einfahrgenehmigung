<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

final readonly class SaveRoleResult
{
    public function __construct(
        public string $roleId,
        public bool $isUpdate,
    ) {
    }
}
