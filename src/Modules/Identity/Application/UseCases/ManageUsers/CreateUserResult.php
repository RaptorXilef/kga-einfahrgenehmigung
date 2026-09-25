<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

/**
 * Ergebnis-DTO nach der Benutzuanlage (enthält die neue ID für den Avatar-Upload und den Rollennamen fürs Audit-Log).
 */
final readonly class CreateUserResult
{
    public function __construct(
        public string $roleName,
        public string $userId,
    ) {
    }
}
