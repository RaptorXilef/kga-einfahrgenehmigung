<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

/**
 * 100% logikfreies View-DTO für die Profilseite (templates/pages/admin/profile.phtml).
 */
final readonly class ProfileViewDto
{
    public function __construct(
        public string $roleName,
        public string $roleNameUpper,
        public string $userId,
        public string $userImage,
        public string $username,
    ) {
    }
}
