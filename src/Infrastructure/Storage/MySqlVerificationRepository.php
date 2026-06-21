<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlVerificationRepository implements VerificationRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadPending(): array
    {
        $data   = $this->loadSql('pending_verification');
        $nowStr = APP_REQUEST_TIME_STR;

        return \array_filter($data, fn (array $item): bool => isset($item['expires']) && $item['expires'] > $nowStr);
    }

    public function savePending(array $data, bool $forceSql = false): void
    {
        $this->saveSql('pending_verification', $data);
    }

    public function loadVerified(): array
    {
        return $this->loadSql('verified_pending');
    }

    public function saveVerified(array $data, bool $forceSql = false): void
    {
        $this->saveSql('verified_pending', $data);
    }

    private function loadSql(string $targetKey): array
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $data = [];
        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $data[$r['token']]            = JsonHelper::decode((string) $r['data']);
            $data[$r['token']]['expires'] = \is_numeric($r['expires']) ? \date('Y-m-d H:i:s', (int) $r['expires']) : $r['expires'];
        }

        return $data;
    }

    private function saveSql(string $targetKey, array $data): void
    {
        $cfg = $this->config->get('storage_config')[$targetKey];
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (token, expires, data) VALUES (?, ?, ?)");
            foreach ($data as $token => $item) {
                $exp = $item['expires'] ?? APP_REQUEST_TIME_STR;
                if (\is_numeric($exp)) {
                    $exp = \date('Y-m-d H:i:s', (int) $exp);
                }
                $stmt->execute([$token, $exp, \json_encode($item, \JSON_UNESCAPED_UNICODE)]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
