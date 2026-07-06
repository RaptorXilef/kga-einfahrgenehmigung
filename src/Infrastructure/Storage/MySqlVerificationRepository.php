<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\VerificationRequest;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlVerificationRepository implements VerificationRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function loadPending(): array
    {
        $data = $this->loadSql('pending_verification');
        $now  = new \DateTimeImmutable();

        return \array_filter($data, fn (VerificationRequest $req): bool => ! $req->isExpired($now));
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

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $token => $row) {
            $exp             = $row['expires'] ?? 'now';
            $dt              = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);
            $payload         = \is_string($row['data'] ?? []) ? $this->jsonHelper->decode($row['data']) : ($row['data'] ?? []);
            $objects[$token] = new VerificationRequest((string) $token, $dt, $payload);
        }
        $this->saveSql('pending_verification', $objects); // Import geht primär auf pending (für Migration)
    }

    private function loadSql(string $targetKey): array
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $data = [];
        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $payload           = \is_string($r['data']) ? $this->jsonHelper->decode($r['data']) : [];
            $exp               = $r['expires'];
            $dt                = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);
            $data[$r['token']] = new VerificationRequest($r['token'], $dt, $payload);
        }

        return $data;
    }

    private function saveSql(string $targetKey, array $requests): void
    {
        $table = $this->config->get('storage_config')[$targetKey]['table'];
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");

            $sql  = null;
            $stmt = null;

            foreach ($requests as $token => $req) {
                $data = [
                    'token'   => $token,
                    'expires' => $req->expiresAt->format('Y-m-d H:i:s'),
                    'data'    => \json_encode($req->data, \JSON_UNESCAPED_UNICODE),
                ];

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $data);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($data);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
