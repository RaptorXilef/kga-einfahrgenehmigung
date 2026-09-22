<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

final readonly class ChangeUserPasswordCommand
{
    public function __construct(
        public string $userId,
        public string $newPassword,
        public ?string $oldPassword = null, // Wenn null, ist es ein Admin-Reset
    ) {
    }
}
