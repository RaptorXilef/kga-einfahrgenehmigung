<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

/**
 * Ergebnis-DTO einer Passwortänderung (liefert den neuen Hash für die Session und den Benutzernamen fürs Audit-Log).
 */
final readonly class ChangeUserPasswordResult
{
    public function __construct(
        public string $passwordHash,
        public string $username,
    ) {
    }
}
