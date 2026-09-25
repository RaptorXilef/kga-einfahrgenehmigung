<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use Override;
use PDO;

/**
 * PDO-Implementierung des Genehmigungs-Archivs.
 */
final readonly class PdoPermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    use DynamicSqlTrait;
    use PermitMapperTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function archivePermits(int $year, array $permitsToArchive): void
    {
        if ($permitsToArchive === []) {
            return;
        }

        $table = (string) ($this->config->getArray('storage_config')['permits_archive']['table'] ?? 'permits_archive');

        foreach ($permitsToArchive as $permit) {
            $item = $this->flattenEntity($permit);
            $sql = $this->buildReplaceSql($table, $item);
            $this->pdo->prepare($sql)->execute($item);
        }
    }

    #[Override]
    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $table = $this->config->getArray('storage_config')['permits_archive']['table'] ?? 'permits_archive';
        $cutoffDate = $this->clock->now()->modify("-{$yearsThreshold} years")->format('Y-m-d H:i:s');

        $sql = "UPDATE `{$table}` SET name = '[ANONYMISIERT]', email = '', kennzeichen = 'XXX-XX 9999', parzelle = 0, is_anonymized = 1 WHERE erstellt <= ? AND is_anonymized = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cutoffDate]);

        return $stmt->rowCount();
    }
}
