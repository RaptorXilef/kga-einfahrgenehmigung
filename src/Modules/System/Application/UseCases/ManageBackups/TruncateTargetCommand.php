<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Command zum vollständigen Leeren einer spezifischen System-Tabelle.
 */
final readonly class TruncateTargetCommand implements CommandInterface
{
    public function __construct(
        public string $targetKey,
    ) {
    }
}
