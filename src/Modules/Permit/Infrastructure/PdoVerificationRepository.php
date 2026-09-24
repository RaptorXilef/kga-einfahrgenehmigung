<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use App\SharedKernel\Infrastructure\Storage\DynamicSqlTrait;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use Exception;
use Override;
use PDO;

final readonly class PdoVerificationRepository implements VerificationRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    #[Override]
    public function loadPending(): array
    {
        $data = $this->loadSql('pending_verification');
        $now = $this->clock->now();

        return \array_filter($data, fn (VerificationRequest $req): bool => !$req->isExpired($now));
    }

    #[Override]
    public function savePending(array $data, bool $forceSql = false): void
    {
        $this->saveSql('pending_verification', $data);
    }

    #[Override]
    public function loadVerified(): array
    {
        $data = $this->loadSql('verified_pending');
        $now = $this->clock->now();

        return \array_filter($data, fn (VerificationRequest $req): bool => !$req->isExpired($now));
    }

    #[Override]
    public function saveVerified(array $data, bool $forceSql = false): void
    {
        $this->saveSql('verified_pending', $data);
    }

    private function loadSql(string $targetKey): array
    {
        $cfg = $this->config->get('storage_config')[$targetKey];
        $data = [];
        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = \is_string($r['data']) ? $this->jsonHelper->decode($r['data']) : [];
            $exp = $r['expires'];
            $dt = \is_numeric($exp) ? $this->clock->now()->setTimestamp((int) $exp) : new DateTimeImmutable($exp);
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

            $sql = null;
            $stmt = null;

            foreach ($requests as $token => $req) {
                $data = [
                    'token' => $token,
                    'expires' => $req->expiresAt->format('Y-m-d H:i:s'),
                    'data' => \json_encode($req->data, \JSON_UNESCAPED_UNICODE),
                ];

                if ($sql === null) {
                    $sql = $this->buildReplaceSql($table, $data);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($data);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
