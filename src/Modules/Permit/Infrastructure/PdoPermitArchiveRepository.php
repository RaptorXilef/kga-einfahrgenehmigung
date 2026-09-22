<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Infrastructure\Storage\DynamicSqlTrait;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use PDO;

final readonly class PdoPermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    use DynamicSqlTrait;
    use PermitMapperTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function findByHash(string $hash): ?Permit
    {
        $hash = \strtoupper(\trim($hash));
        $table = $this->config->get('storage_config')['permits_archive']['table'];

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $this->mapToEntity($row);
        }

        $searchParts = \explode('-', $hash);
        $searchId = \end($searchParts);

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code LIKE ? OR code = ?");
        $stmt->execute(['%-' . $searchId, $searchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapToEntity($row) : null;
    }

    public function isCodeInArchive(string $code): bool
    {
        $table = $this->config->get('storage_config')['permits_archive']['table'];
        $stmt = $this->pdo->prepare("SELECT code FROM `{$table}` WHERE code = ?");
        $stmt->execute([$code]);

        return (bool) $stmt->fetch();
    }

    public function archivePermits(int $year, array $permitsToArchive): void
    {
        if ($permitsToArchive === []) {
            return;
        }

        $table = $this->config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';

        foreach ($permitsToArchive as $permit) {
            $item = $this->flattenEntity($permit);
            $sql = $this->buildReplaceSql($table, $item);
            $this->pdo->prepare($sql)->execute($item);
        }
    }

    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $table = $this->config->get('storage_config')['permits_archive']['table'];
        $now = \defined('APP_REQUEST_TIME') ? APP_REQUEST_TIME : \time();
        $cutoffDate = \date('Y-m-d H:i:s', \strtotime("-{$yearsThreshold} years", $now));

        $sql = "UPDATE `{$table}` SET name = '[ANONYMISIERT]', email = '', kennzeichen = 'XXX-XX 9999', parzelle = 0, is_anonymized = 1 WHERE erstellt <= ? AND is_anonymized = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cutoffDate]);

        return $stmt->rowCount();
    }

    public function getArchivedPermits(int $minYear): array
    {
        $table = $this->config->get('storage_config')['permits_archive']['table'];
        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE YEAR(erstellt) >= ? OR YEAR(von) >= ?");
        $stmt->execute([$minYear, $minYear]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!\is_array($rows)) {
            return [];
        }

        return \array_map($this->mapToEntity(...), $rows);
    }
}
