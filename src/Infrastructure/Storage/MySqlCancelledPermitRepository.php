<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\Permit;

final readonly class MySqlCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use StorageMapperTrait;
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function saveCancelled(Permit $permit): void
    {
        $table = $this->config->get('storage_config')['permits_cancelled']['table'];
        $item  = $this->flattenEntity($permit);

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

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $key => $item) {
            if (! isset($item['code'])) {
                $item['code'] = $key;
            }
            $objects[] = $this->mapToEntity($item);
        }

        $table = $this->config->get('storage_config')['permits_cancelled']['table'];
        $this->pdo->beginTransaction();

        try {
            $sql  = null;
            $stmt = null;
            foreach ($objects as $permit) {
                $item                  = $this->flattenEntity($permit);
                $item['is_anonymized'] = 1;
                $item['agreements'] ??= '{}';

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $item);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($item);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
