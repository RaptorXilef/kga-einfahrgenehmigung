<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;

// TODO DOCBLOCK
final readonly class RateLimiter implements RateLimiterInterface
{
    private const int MAX_ATTEMPTS    = 5;
    private const int LOCKOUT_MINUTES = 15;

    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    // TODO DOCBLOCK
    public function isBlocked(string $ip): bool
    {
        $cfg = $this->config->get('storage_config')['login_attempts'];
        $now = new \DateTimeImmutable();

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare("SELECT attempts, last_attempt FROM {$cfg['table']} WHERE ip_address = ?");
            $stmt->execute([$ip]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $lastAttempt = new \DateTimeImmutable($row['last_attempt']);
                $diffMinutes = ($now->getTimestamp() - $lastAttempt->getTimestamp()) / 60;

                if ($diffMinutes > self::LOCKOUT_MINUTES) {
                    $this->clearAttempts($ip);

                    return false;
                }

                return (int) $row['attempts'] >= self::MAX_ATTEMPTS;
            }

            return false;
        }

        // JSON Fallback
        $path = $this->getFilePath($cfg['file']);
        $data = \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];

        if (isset($data[$ip])) {
            $lastAttempt = new \DateTimeImmutable($data[$ip]['last_attempt']);
            $diffMinutes = ($now->getTimestamp() - $lastAttempt->getTimestamp()) / 60;

            if ($diffMinutes > self::LOCKOUT_MINUTES) {
                $this->clearAttempts($ip);

                return false;
            }

            return (int) $data[$ip]['attempts'] >= self::MAX_ATTEMPTS;
        }

        return false;
    }

    // TODO DOCBLOCK
    public function recordFailedAttempt(string $ip): void
    {
        $cfg    = $this->config->get('storage_config')['login_attempts'];
        $nowStr = \date('Y-m-d H:i:s');

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $sql = "INSERT INTO {$cfg['table']} (ip_address, attempts, last_attempt)
                    VALUES (?, 1, ?)
                    ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = VALUES(last_attempt)";
            $this->pdo->prepare($sql)->execute([$ip, $nowStr]);

            return;
        }

        $path = $this->getFilePath($cfg['file']);
        $data = \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];

        if (! isset($data[$ip])) {
            $data[$ip] = ['attempts' => 1, 'last_attempt' => $nowStr];
        } else {
            ++$data[$ip]['attempts'];
            $data[$ip]['last_attempt'] = $nowStr;
        }

        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT));
    }

    // TODO DOCBLOCK
    public function clearAttempts(string $ip): void
    {
        $cfg = $this->config->get('storage_config')['login_attempts'];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $this->pdo->prepare("DELETE FROM {$cfg['table']} WHERE ip_address = ?")->execute([$ip]);

            return;
        }

        $path = $this->getFilePath($cfg['file']);
        $data = \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];

        if (isset($data[$ip])) {
            unset($data[$ip]);
            \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT));
        }
    }

    // TODO DOCBLOCK
    private function getFilePath(string $fileName): string
    {
        return \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
            \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $fileName;
    }
}
