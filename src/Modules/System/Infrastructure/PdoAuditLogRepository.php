<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Domain\AuditLog;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\IpAddress;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use DateTimeImmutable;
use Override;
use PDO;

final readonly class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function save(AuditLog $log): void
    {
        $table = $this->config->get('storage_config')['audit_logs']['table'] ?? 'audit_logs';

        $data = [
            'id' => $log->id,
            'user_id' => $log->userId,
            'username' => $log->username,
            'action' => $log->action,
            'details' => $log->details,
            'ip_address' => $log->ipAddress->value,
            'created_at' => $log->createdAt->format('Y-m-d H:i:s'),
        ];

        $sql = $this->buildInsertUpdateSql($table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    #[Override]
    public function getPaginated(int $page, int $limit, string $actionFilter = ''): array
    {
        $table = $this->config->get('storage_config')['audit_logs']['table'] ?? 'audit_logs';
        $where = '';
        $params = [];

        if ($actionFilter !== '') {
            $where = 'WHERE action = ?';
            $params[] = $actionFilter;
        }

        $offset = ($page - 1) * $limit;

        // Total Count holen
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` {$where}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "SELECT * FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = new AuditLog(
                (string) $r['id'],
                (string) $r['user_id'],
                (string) $r['username'],
                (string) $r['action'],
                (string) $r['details'],
                new IpAddress(!empty($r['ip_address']) && $r['ip_address'] !== 'unknown' ? (string) $r['ip_address'] : '0.0.0.0'),
                new DateTimeImmutable((string) $r['created_at']),
            );
        }

        return ['items' => $items, 'total' => $total];
    }
}
