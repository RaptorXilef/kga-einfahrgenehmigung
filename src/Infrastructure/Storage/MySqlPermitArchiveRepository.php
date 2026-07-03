<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlPermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    use DynamicSqlTrait;
    use StorageMapperTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function isCodeInArchive(string $code): bool
    {
        $table = $this->config->get('storage_config')['permits_archive']['table'];
        $stmt  = $this->pdo->prepare("SELECT code FROM `{$table}` WHERE code = ?");
        $stmt->execute([$code]);

        return (bool) $stmt->fetch();
    }

    public function archivePermits(int $year, array $permitsToArchive): void
    {
        if (empty($permitsToArchive)) {
            return;
        }

        $table = $this->config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';

        foreach ($permitsToArchive as $permit) {
            $item = $this->flattenEntity($permit);

            // Magie: Das SQL generiert sich selbst aus den Schlüsseln von $item
            $sql = $this->buildReplaceSql($table, $item);

            // PDO bindet das Array automatisch an die :platzhalter!
            $this->pdo->prepare($sql)->execute($item);
        }
    }

    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $table      = $this->config->get('storage_config')['permits_archive']['table'];
        $cutoffDate = \date('Y-m-d H:i:s', \strtotime("-{$yearsThreshold} years", APP_REQUEST_TIME));

        $sql  = "UPDATE `{$table}` SET name = '[ANONYMISIERT]', email = '[ANONYMISIERT]', kennzeichen = '[ANONYMISIERT]', parzelle = '0000', is_anonymized = 1 WHERE erstellt <= ? AND is_anonymized = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cutoffDate]);

        return $stmt->rowCount();
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
        $this->archivePermits(0, $objects);
    }
}
