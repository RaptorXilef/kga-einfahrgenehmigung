<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Contracts\Storage\BackupServiceInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use Override;

/**
 * Sichert die Zieltabelle vorab als ZIP und leert sie anschließend über den BackupService.
 * VSA CQRS FIX: Kein direkter PDO-Zugriff mehr im CommandHandler.
 *
 * @implements CommandWithResultHandlerInterface<TruncateTargetCommand, string>
 */
final readonly class TruncateTargetHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
    ) {
    }

    /**
     * @param TruncateTargetCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): string
    {
        return $this->backupService->truncateTarget($command->targetKey);
    }
}
