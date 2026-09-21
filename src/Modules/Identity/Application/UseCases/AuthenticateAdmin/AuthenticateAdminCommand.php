<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class AuthenticateAdminCommand implements CommandInterface
{
    public function __construct(
        public string $username,
        public string $password,
        public string $ipAddress,
    ) {
    }
}
