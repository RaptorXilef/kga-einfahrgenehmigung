<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

final readonly class DeleteUserCommand
{
    public function __construct(
        public string $userId,
        public string $initiatorId,
    ) {
    }
}
