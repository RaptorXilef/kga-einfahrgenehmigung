<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Core\Entity\Permit;

final readonly class MySqlCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use StorageMapperTrait;
    use DynamicSqlTrait;

    public function __construct(private \PDO $pdo, private ConfigInterface $config)
    {
    }

    public function saveCancelled(Permit $permit): void
    {
        $table = $this->config->get('storage_config')['permits_cancelled']['table'];

        $item                  = $this->flattenEntity($permit);
        $item['is_anonymized'] = 1;
        $item['agreements'] ??= '{}';

        $sql = $this->buildReplaceSql($table, $item);
        $this->pdo->prepare($sql)->execute($item);
    }

    public function isCodeCancelled(string $code): bool
    {
        $table = $this->config->get('storage_config')['permits_cancelled']['table'];
        $stmt  = $this->pdo->prepare("SELECT code FROM `{$table}` WHERE code = ?");
        $stmt->execute([$code]);

        return (bool) $stmt->fetch();
    }

    public function loadAll(): array
    {
        $table = $this->config->get('storage_config')['permits_cancelled']['table'];
        $stmt  = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY erstellt DESC");

        return \array_map($this->mapToEntity(...), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
