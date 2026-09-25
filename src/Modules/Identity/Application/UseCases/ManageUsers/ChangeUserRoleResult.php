<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

/**
 * Ergebnis-DTO nach einer Rollenänderung für das Audit-Log und die Flash-Message.
 */
final readonly class ChangeUserRoleResult
{
    public function __construct(
        public string $newRoleName,
        public string $oldRoleName,
        public string $username,
    ) {
    }
}
