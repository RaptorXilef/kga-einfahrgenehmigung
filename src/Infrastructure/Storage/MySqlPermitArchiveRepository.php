<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\Permit;
use PDO;

final readonly class MySqlPermitArchiveRepository implements PermitArchiveRepositoryInterface
{
    use DynamicSqlTrait;
    use StorageMapperTrait;

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

        // 1. Direkter Match
        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $this->mapToEntity($row);
        }

        // 2. Fallback: Suche nach der extrahierten ID am Ende des Codes (wie im Main Storage)
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

            // Magie: Das SQL generiert sich selbst aus den Schlüsseln von $item
            $sql = $this->buildReplaceSql($table, $item);

            // PDO bindet das Array automatisch an die :platzhalter!
            $this->pdo->prepare($sql)->execute($item);
        }
    }

    public function anonymizeOldRecords(int $yearsThreshold = 10): int
    {
        $table = $this->config->get('storage_config')['permits_archive']['table'];

        // Konstante APP_REQUEST_TIME (aus dem Bootstrapper) nutzen oder Fallback
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

        // Sucht alle Einträge, deren Erstell- oder Gültigkeitsjahr ab dem gesuchten Jahr beginnt
        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE YEAR(erstellt) >= ? OR YEAR(von) >= ?");
        $stmt->execute([$minYear, $minYear]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!\is_array($rows)) {
            return [];
        }

        // Nutzt die magische `mapToEntity` Methode aus dem StorageMapperTrait!
        return \array_map($this->mapToEntity(...), $rows);
    }
}
