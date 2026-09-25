<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Modules\Permit\Domain\CancelledPermitRepositoryInterface;
use App\Modules\Permit\Domain\Permit;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use Override;
use PDO;

final readonly class PdoCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use PermitMapperTrait;
    use DynamicSqlTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    #[Override]
    public function findByHash(string $hash): ?Permit
    {
        $hash = \strtoupper(\trim($hash));
        $table = $this->config->getArray('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled';

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (\is_array($row)) {
            return $this->mapToEntity($row);
        }

        $searchParts = \explode('-', $hash);
        $searchId = \end($searchParts);

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code LIKE ? OR code = ?");
        $stmt->execute(['%-' . $searchId, $searchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return \is_array($row) ? $this->mapToEntity($row) : null;
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

    #[Override]
    public function isCodeCancelled(string $code): bool
    {
        $table = $this->config->getArray('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled';
        $stmt = $this->pdo->prepare("SELECT code FROM `{$table}` WHERE code = ?");
        $stmt->execute([$code]);

        return (bool) $stmt->fetch();
    }

    #[Override]
    public function loadAll(): array
    {
        $table = $this->config->getArray('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled';
        $stmt = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY erstellt DESC");

        $permits = [];
        if ($stmt !== false) {
            while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $permits[] = $this->mapToEntity($row);
            }
        }

        return $permits;
    }
}
