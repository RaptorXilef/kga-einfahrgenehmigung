<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;
use PDO;

/**
 * Sichert die Zieltabelle vorab als ZIP und leert sie anschließend via TRUNCATE.
 *
 * @implements CommandWithResultHandlerInterface<TruncateTargetCommand, string>
 */
final readonly class TruncateTargetHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private BackupServiceInterface $backupService,
    ) {
    }

    /**
     * @param TruncateTargetCommand $command
     */
    #[Override]
    public function handle(mixed $command): string
    {
        $storageConfig = $this->config->getArray('storage_config');
        $targetKey = $command->targetKey;

        if (
            $targetKey === ''
            || $targetKey === 'all'
            || !isset($storageConfig[$targetKey])
            || !\is_array($storageConfig[$targetKey])
            || !isset($storageConfig[$targetKey]['table'])
        ) {
            throw new DomainException('Ungültige oder nicht erlaubte Zieltabelle ausgewählt.');
        }

        $table = (string) $storageConfig[$targetKey]['table'];

        // Sicherheits-Backup der Tabelle vor dem Leeren erstellen
        $this->backupService->createBackup($targetKey);

        $this->pdo->exec("TRUNCATE TABLE `{$table}`");

        return $table;
    }
}
