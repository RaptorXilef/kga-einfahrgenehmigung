<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Command zum Speichern der zuletzt gesehenen Changelog-Version eines Benutzers.
 */
final readonly class MarkChangelogReadCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $version,
    ) {
    }
}
