<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\GetUserManagementData;

/**
 * 100% logikfreies View-DTO für einen Listeneintrag im Tab "Benutzer".
 */
final readonly class UserListDto
{
    public function __construct(
        public string $id,
        public string $idHash,
        public string $username,
        public string $displayName,
        public string $roleName,
        public string $avatarUrl,
        public array $roleOptions,
        public string $confirmDeleteMsg,
    ) {
    }
}
