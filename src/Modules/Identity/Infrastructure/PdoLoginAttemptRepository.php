<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Modules\Identity\Domain\LoginAttempt;
use App\Modules\Identity\Domain\LoginAttemptRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\IpAddress;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use DateTimeImmutable;
use Override;
use PDO;

final readonly class PdoLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function findByIp(string $ip): ?LoginAttempt
    {
        $table = $this->config->getArray('storage_config')['login_attempts']['table'] ?? 'login_attempts';
        $stmt = $this->pdo->prepare("SELECT attempts, last_attempt FROM `{$table}` WHERE ip_address = ?");
        $stmt->execute([$ip]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (\is_array($row)) {
            return new LoginAttempt(
                new IpAddress($ip === 'unknown' || $ip === '' ? '0.0.0.0' : $ip),
                (int) $row['attempts'],
                new DateTimeImmutable((string) $row['last_attempt']),
            );
        }

        return null;
    }

    #[Override]
    public function save(LoginAttempt $attempt): void
    {
        $table = $this->config->getArray('storage_config')['login_attempts']['table'] ?? 'login_attempts';
        $data = [
            'ip_address' => $attempt->ipAddress->value,
            'attempts' => $attempt->attempts,
            'last_attempt' => $attempt->lastAttempt->format('Y-m-d H:i:s'),
        ];

        $sql = $this->buildInsertUpdateSql((string) $table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    #[Override]
    public function deleteByIp(string $ip): void
    {
        $table = $this->config->getArray('storage_config')['login_attempts']['table'] ?? 'login_attempts';
        $this->pdo->prepare("DELETE FROM `{$table}` WHERE ip_address = ?")->execute([$ip]);
    }

    #[Override]
    public function deleteOlderThan(int $minutes): void
    {
        $table = $this->config->getArray('storage_config')['login_attempts']['table'] ?? 'login_attempts';
        $this->pdo->prepare("DELETE FROM `{$table}` WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE)")->execute([$minutes]);
    }
}
