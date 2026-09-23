<?php

declare(strict_types=1);

namespace App\Contracts\Security;

interface AuthorizationInterface
{
    public function hasPermission(string $permission): bool;

    public function isLoggedIn(): bool;
}
