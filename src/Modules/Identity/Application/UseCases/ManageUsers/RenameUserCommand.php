<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

final readonly class RenameUserCommand
{
    public function __construct(
        public string $userId,
        public string $newUsername,
    ) {
    }
}
