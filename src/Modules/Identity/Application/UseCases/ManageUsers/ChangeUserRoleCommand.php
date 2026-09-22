<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class ChangeUserRoleCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $newRoleId,
    ) {
    }
}
