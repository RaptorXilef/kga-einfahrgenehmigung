<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Domain\AuditLog;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use Override;
use PDO;

/**
 * PDO-Implementierung des Audit-Log Write-Repositories.
 */
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
        $storageConfig = $this->config->getArray('storage_config');
        $table = (string) ($storageConfig['audit_logs']['table'] ?? 'audit_logs');

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
}
