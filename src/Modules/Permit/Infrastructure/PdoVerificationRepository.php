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
use Override;
use PDO;

/**
 * PDO-Implementierung für Double-Opt-In- und Checkout-Verifizierungen.
 */
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
        $this->deleteExpiredSql('pending_verification');
        $data = $this->loadSql('pending_verification');
        $now = $this->clock->now();

        return \array_filter($data, fn (VerificationRequest $req): bool => !$req->isExpired($now));
    }

    #[Override]
    public function findPendingByTokenOrCode(string $tokenOrCode): ?VerificationRequest
    {
        return $this->findByTokenOrCodeSql('pending_verification', $tokenOrCode);
    }

    #[Override]
    public function savePendingOne(VerificationRequest $request): void
    {
        $this->saveOneSql('pending_verification', $request);
    }

    #[Override]
    public function deletePending(string $token): void
    {
        $this->deleteOneSql('pending_verification', $token);
    }

    #[Override]
    public function findVerifiedByToken(string $token): ?VerificationRequest
    {
        $this->deleteExpiredSql('verified_pending');
        $table = $this->resolveTableName('verified_pending');

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!\is_array($row)) {
            return null;
        }

        $req = $this->mapRowToRequest($row);

        return $req->isExpired($this->clock->now()) ? null : $req;
    }

    #[Override]
    public function findVerifiedByTokenOrCode(string $tokenOrCode): ?VerificationRequest
    {
        return $this->findByTokenOrCodeSql('verified_pending', $tokenOrCode);
    }

    #[Override]
    public function saveVerifiedOne(VerificationRequest $request): void
    {
        $this->saveOneSql('verified_pending', $request);
    }

    #[Override]
    public function deleteVerified(string $token): void
    {
        $this->deleteOneSql('verified_pending', $token);
    }

    private function resolveTableName(string $targetKey): string
    {
        $cfg = $this->config->getArray('storage_config')[$targetKey] ?? [];

        return (string) ($cfg['table'] ?? $targetKey);
    }

    private function deleteExpiredSql(string $targetKey): void
    {
        $table = $this->resolveTableName($targetKey);
        $nowStr = $this->clock->now()->format('Y-m-d H:i:s');

        $this->pdo->prepare("DELETE FROM `{$table}` WHERE expires < :now")->execute(['now' => $nowStr]);
    }

    private function findByTokenOrCodeSql(string $targetKey, string $tokenOrCode): ?VerificationRequest
    {
        $this->deleteExpiredSql($targetKey);

        $input = \strtoupper(\trim($tokenOrCode));
        if ($input === '') {
            return null;
        }

        $table = $this->resolveTableName($targetKey);
        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE UPPER(token) = :token OR data LIKE :codeLike");
        $stmt->execute([
            'token' => $input,
            'codeLike' => '%' . $input . '%',
        ]);

        $now = $this->clock->now();
        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $req = $this->mapRowToRequest($row);
            if ($req->isExpired($now)) {
                continue;
            }

            if (
                \strtoupper($req->token) === $input
                || \strtoupper((string) ($req->data['verification_code'] ?? '')) === $input
            ) {
                return $req;
            }
        }

        return null;
    }

    private function saveOneSql(string $targetKey, VerificationRequest $req): void
    {
        $table = $this->resolveTableName($targetKey);
        $data = [
            'token' => $req->token,
            'expires' => $req->expiresAt->format('Y-m-d H:i:s'),
            'data' => \json_encode($req->data, \JSON_UNESCAPED_UNICODE),
        ];

        $sql = $this->buildReplaceSql($table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    private function deleteOneSql(string $targetKey, string $token): void
    {
        $table = $this->resolveTableName($targetKey);
        $this->pdo->prepare("DELETE FROM `{$table}` WHERE token = :token")->execute(['token' => $token]);
    }

    /**
     * @param array<string, mixed> $r
     */
    private function mapRowToRequest(array $r): VerificationRequest
    {
        $payload = \is_string($r['data'] ?? null) ? $this->jsonHelper->decode($r['data']) : [];
        $exp = $r['expires'] ?? '';
        $dt = \is_numeric($exp) ? $this->clock->now()->setTimestamp((int) $exp) : new DateTimeImmutable((string) $exp);

        return new VerificationRequest((string) $r['token'], $dt, $payload);
    }

    /**
     * @return array<string, VerificationRequest>
     */
    private function loadSql(string $targetKey): array
    {
        $table = $this->resolveTableName($targetKey);
        $data = [];
        $stmt = $this->pdo->query("SELECT * FROM `{$table}`");

        if ($stmt !== false) {
            while (\is_array($r = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $req = $this->mapRowToRequest($r);
                $data[$req->token] = $req;
            }
        }

        return $data;
    }
}
