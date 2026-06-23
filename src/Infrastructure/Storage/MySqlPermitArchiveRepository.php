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
        $table = $this->config->get('storage_config')['permits_archive']['table'];
        $sql   = "REPLACE INTO `{$table}` (code, template_key, name, email, kennzeichen, parzelle, typ, firma, zweck, preis, von, bis, status, erstellt, interner_kommentar, is_anonymized, agreements) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt  = $this->pdo->prepare($sql);
        foreach ($permitsToArchive as $permit) {
            $item = $this->flattenEntity($permit);
            $stmt->execute([$item['code'], $item['template_key'], $item['name'], $item['email'], $item['kennzeichen'], $item['parzelle'], $item['typ'], $item['firma'], $item['zweck'], $item['preis'], $item['von'], $item['bis'], $item['status'], $item['erstellt'], $item['interner_kommentar'], $item['is_anonymized'] ?? 0, $item['agreements'] ?? '{}']);
        }
    }

    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $table      = $this->config->get('storage_config')['permits_archive']['table'];
        $cutoffDate = \date('Y-m-d H:i:s', \strtotime("-{$yearsThreshold} years", APP_REQUEST_TIME));
        $sql        = "UPDATE `{$table}` SET name = '[ANONYMISIERT]', email = '[ANONYMISIERT]', kennzeichen = '[ANONYMISIERT]', parzelle = '0000', is_anonymized = 1 WHERE erstellt <= ? AND is_anonymized = 0";
        $stmt       = $this->pdo->prepare($sql);
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
