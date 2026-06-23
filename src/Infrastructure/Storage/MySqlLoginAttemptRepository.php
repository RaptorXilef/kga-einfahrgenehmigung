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
        $sql   = "INSERT INTO `{$table}` (ip_address, attempts, last_attempt) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), last_attempt = VALUES(last_attempt)";
        $this->pdo->prepare($sql)->execute([$attempt->ipAddress, $attempt->attempts, $attempt->lastAttempt->format('Y-m-d H:i:s')]);
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
            $stmt = $this->pdo->prepare("REPLACE INTO `{$table}` (ip_address, attempts, last_attempt) VALUES (?, ?, ?)");
            foreach ($data as $ip => $item) {
                $stmt->execute([$ip, (int) ($item['attempts'] ?? 0), $item['last_attempt'] ?? 'now']);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
