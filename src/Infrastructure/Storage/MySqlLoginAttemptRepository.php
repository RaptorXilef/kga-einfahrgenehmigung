<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\LoginAttemptRepositoryInterface;
use App\Core\Entity\LoginAttempt;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function findByIp(string $ip): ?LoginAttempt
    {
        $table = $this->config->get('storage_config')['login_attempts']['table'];
        $stmt  = $this->pdo->prepare("SELECT attempts, last_attempt FROM `{$table}` WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return new LoginAttempt($ip, (int) $row['attempts'], new \DateTimeImmutable($row['last_attempt']));
        }

        return null;
    }

    public function save(LoginAttempt $attempt): void
    {
        $table = $this->config->get('storage_config')['login_attempts']['table'];
        $data  = [
            'ip_address'   => $attempt->ipAddress,
            'attempts'     => $attempt->attempts,
            'last_attempt' => $attempt->lastAttempt->format('Y-m-d H:i:s'),
        ];

        $sql = $this->buildInsertUpdateSql($table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    public function deleteByIp(string $ip): void
    {
        $table = $this->config->get('storage_config')['login_attempts']['table'];
        $this->pdo->prepare("DELETE FROM `{$table}` WHERE ip_address = ?")->execute([$ip]);
    }

    public function deleteOlderThan(int $minutes): void
    {
        $table = $this->config->get('storage_config')['login_attempts']['table'];
        $this->pdo->prepare("DELETE FROM `{$table}` WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE)")->execute([$minutes]);
    }

    public function import(array $data): void
    {
        $table = $this->config->get('storage_config')['login_attempts']['table'];
        $this->pdo->beginTransaction();

        try {
            $sql  = null;
            $stmt = null;

            foreach ($data as $ip => $item) {
                $mapped = [
                    'ip_address'   => $ip,
                    'attempts'     => (int) ($item['attempts'] ?? 0),
                    'last_attempt' => $item['last_attempt'] ?? 'now',
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
