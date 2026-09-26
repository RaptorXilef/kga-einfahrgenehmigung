<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetAuditLogsData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Liest die Audit-Logs direkt per SQL-Paginierung über PDO aus und mappt sie ohne Entity-Overhead in flache View-DTOs.
 *
 * @implements QueryHandlerInterface<GetAuditLogsDataQuery, AuditLogsResultDto>
 */
final readonly class GetAuditLogsDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ImageStorageInterface $imageStorage,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetAuditLogsDataQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): AuditLogsResultDto
    {
        $storageConfig = $this->config->getArray('storage_config');
        $table = (string) ($storageConfig['audit_logs']['table'] ?? 'audit_logs');
        $where = '';
        $params = [];

        if ($query->actionFilter !== '') {
            $where = 'WHERE action = ?';
            $params[] = $query->actionFilter;
        }

        $safeLimit = \max(1, $query->limit);
        $safePage = \max(1, $query->page);
        $offset = ($safePage - 1) * $safeLimit;

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` {$where}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "SELECT * FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT {$safeLimit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        while (\is_array($r = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $createdAtRaw = \trim((string) ($r['created_at'] ?? ''));
            $dt = $createdAtRaw !== '' ? new DateTimeImmutable($createdAtRaw) : $this->clock->now();
            $userId = (string) ($r['user_id'] ?? '');
            $ipRaw = isset($r['ip_address']) ? \trim((string) $r['ip_address']) : '';
            $safeIp = $ipRaw !== '' && $ipRaw !== 'unknown' ? $ipRaw : '0.0.0.0';

            $items[] = new AuditLogViewDto(
                dateFormatted: $dt->format('d.m.Y'),
                timeFormatted: $dt->format('H:i:s'),
                action: (string) ($r['action'] ?? ''),
                details: (string) ($r['details'] ?? ''),
                username: (string) ($r['username'] ?? ''),
                userId: $userId,
                ipAddress: $safeIp,
                avatarUrl: $this->imageStorage->getImageUrl('user', $userId, 'user.webp'),
            );
        }

        return new AuditLogsResultDto(
            items: $items,
            total: $total,
        );
    }
}
