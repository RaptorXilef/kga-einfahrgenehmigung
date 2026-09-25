<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Query zum Abrufen der Profildaten des aktuell angemeldeten Benutzers.
 */
final readonly class GetProfileDataQuery implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
