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
final readonly class JsonLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    use JsonTransactionTrait;

    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function findByIp(string $ip): ?LoginAttempt
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['login_attempts']['file']);
        if (! \file_exists($path)) {
            return null;
        }
        $data = JsonHelper::read($path);
        if (isset($data[$ip])) {
            return new LoginAttempt($ip, (int) $data[$ip]['attempts'], new \DateTimeImmutable($data[$ip]['last_attempt']));
        }

        return null;
    }

    public function save(LoginAttempt $attempt): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['login_attempts']['file']);
        $this->executeJsonTransaction($path, function (array &$data) use ($attempt): bool {
            $data[$attempt->ipAddress] = ['attempts' => $attempt->attempts, 'last_attempt' => $attempt->lastAttempt->format('Y-m-d H:i:s')];

            return true;
        });
    }

    public function deleteByIp(string $ip): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['login_attempts']['file']);
        $this->executeJsonTransaction($path, function (array &$data) use ($ip): bool {
            if (isset($data[$ip])) {
                unset($data[$ip]);

                return true;
            }

            return false;
        });
    }

    public function deleteOlderThan(int $minutes): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['login_attempts']['file']);
        $this->executeJsonTransaction($path, function (array &$data) use ($minutes): bool {
            $threshold = \time() - ($minutes * 60);
            foreach ($data as $ip => $info) {
                if (isset($info['last_attempt']) && \strtotime($info['last_attempt']) < $threshold) {
                    unset($data[$ip]);
                }
            }

            return true;
        });
    }

    public function import(array $data): void
    {
        $path = $this->config->getStoragePath($this->config->get('storage_config')['login_attempts']['file']);
        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
    }
}
