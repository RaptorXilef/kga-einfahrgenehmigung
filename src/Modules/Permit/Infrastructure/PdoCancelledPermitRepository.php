<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\CancelledPermitRepositoryInterface;
use App\Modules\Permit\Domain\Permit;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use Override;
use PDO;

/**
 * PDO-Implementierung für stornierte Genehmigungen.
 */
final readonly class PdoCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use PermitMapperTrait;
    use DynamicSqlTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    #[Override]
    public function saveCancelled(Permit $permit): void
    {
        $table = (string) ($this->config->getArray('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled');
        $item = $this->flattenEntity($permit);

        $item['is_anonymized'] = 1;
        $item['agreements'] ??= '{}';

        $sql = $this->buildReplaceSql($table, $item);
        $this->pdo->prepare($sql)->execute($item);
    }
}
