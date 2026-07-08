<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Core\Entity\AuditLog;
use App\Core\ValueObject\IpAddress;

final readonly class MySqlAuditLogRepository implements AuditLogRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function save(AuditLog $log): void
    {
        $table = $this->config->get('storage_config')['audit_logs']['table'] ?? 'audit_logs';

        $data = [
            'id'         => $log->id,
            'user_id'    => $log->userId,
            'username'   => $log->username,
            'action'     => $log->action,
            'details'    => $log->details,
            'ip_address' => $log->ipAddress->value,
            'created_at' => $log->createdAt->format('Y-m-d H:i:s'),
        ];

        $sql = $this->buildInsertUpdateSql($table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    public function getPaginated(int $page, int $limit, string $actionFilter = ''): array
    {
        $table  = $this->config->get('storage_config')['audit_logs']['table'] ?? 'audit_logs';
        $where  = '';
        $params = [];

        if ($actionFilter !== '') {
            $where    = 'WHERE action = ?';
            $params[] = $actionFilter;
        }

        $offset = ($page - 1) * $limit;

        // Total Count holen
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` $where");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Items holen
        $sql  = "SELECT * FROM `{$table}` $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $items[] = new AuditLog(
                $r['id'],
                $r['user_id'],
                $r['username'],
                $r['action'],
                $r['details'],
                new IpAddress(! empty($r['ip_address']) && $r['ip_address'] !== 'unknown' ? $r['ip_address'] : '0.0.0.0'),
                new \DateTimeImmutable($r['created_at']),
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function import(array $data): void
    {
        $table = $this->config->get('storage_config')['audit_logs']['table'] ?? 'audit_logs';
        $this->pdo->beginTransaction();

        try {
            $sql  = null;
            $stmt = null;

            foreach ($data as $id => $item) {
                $mapped = [
                    'id'         => $id,
                    'user_id'    => $item['user_id'] ?? '',
                    'username'   => $item['username'] ?? '',
                    'action'     => $item['action'] ?? '',
                    'details'    => $item['details'] ?? '',
                    'ip_address' => $item['ip_address'] ?? '0.0.0.0',
                    'created_at' => $item['created_at'] ?? '',
                ];

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $mapped);
                    $stmt = $this->pdo->prepare($sql);
                }

                $stmt->execute($mapped);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
