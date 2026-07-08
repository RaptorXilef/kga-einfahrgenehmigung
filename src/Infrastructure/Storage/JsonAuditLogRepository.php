<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\AuditLog;
use App\Core\ValueObject\IpAddress;

final readonly class JsonAuditLogRepository implements AuditLogRepositoryInterface
{
    use JsonTransactionTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function save(AuditLog $log): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['audit_logs']['file'] ?? 'audit_logs.json');

        $this->executeJsonTransaction($path, function (array &$data) use ($log): bool {
            // Speichere maximal 5000 Einträge im JSON um Speicher-Lags zu verhindern
            if (\count($data) >= 5000) {
                \array_shift($data);
            }

            $data[] = [
                'id'         => $log->id,
                'user_id'    => $log->userId,
                'username'   => $log->username,
                'action'     => $log->action,
                'details'    => $log->details,
                'ip_address' => $log->ipAddress->value,
                'created_at' => $log->createdAt->format('Y-m-d H:i:s'),
            ];

            return true;
        });
    }

    public function getPaginated(int $page, int $limit, string $actionFilter = ''): array
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['audit_logs']['file'] ?? 'audit_logs.json');

        if (! \file_exists($path)) {
            return ['items' => [], 'total' => 0];
        }

        $data = $this->jsonHelper->read($path);

        if ($actionFilter !== '') {
            $data = \array_filter($data, fn ($item) => ($item['action'] ?? '') === $actionFilter);
        }

        // Neueste zuerst
        \usort($data, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        $total  = \count($data);
        $offset = ($page - 1) * $limit;
        $sliced = \array_slice($data, $offset, $limit);

        $items = \array_map(fn ($r) => new AuditLog(
            $r['id'],
            $r['user_id'],
            $r['username'],
            $r['action'],
            $r['details'],
            new IpAddress(! empty($r['ip_address']) && $r['ip_address'] !== 'unknown' ? $r['ip_address'] : '0.0.0.0'),
            new \DateTimeImmutable($r['created_at']),
        ), $sliced);

        return ['items' => $items, 'total' => $total];
    }

    public function import(array $data): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['audit_logs']['file'] ?? 'audit_logs.json');
        \file_put_contents($path, \json_encode(\array_values($data), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
    }
}
