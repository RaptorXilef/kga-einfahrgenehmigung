<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\MagicLink;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;
use Override;
use PDO;

final readonly class PdoMagicLinkRepository implements MagicLinkRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    #[Override]
    public function save(MagicLink $magicLink): void
    {
        $sql = 'REPLACE INTO magic_links (token, email, code, expires) VALUES (:token, :email, :code, :expires)';
        $this->pdo->prepare($sql)->execute([
            'token' => $magicLink->token,
            'email' => $magicLink->email->value,
            'code' => $magicLink->code,
            'expires' => $magicLink->expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    #[Override]
    public function findByInput(string $input): ?MagicLink
    {
        $stmt = $this->pdo->prepare('SELECT * FROM magic_links WHERE token = :input OR code = :input LIMIT 1');
        $stmt->execute(['input' => $input]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!\is_array($row)) {
            return null;
        }

        return new MagicLink(
            (string) $row['token'],
            new EmailAddress((string) $row['email']),
            (string) $row['code'],
            new DateTimeImmutable((string) $row['expires']),
        );
    }

    #[Override]
    public function delete(string $token): void
    {
        $this->pdo->prepare('DELETE FROM magic_links WHERE token = :token')->execute(['token' => $token]);
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): void
    {
        $this->pdo->prepare('DELETE FROM magic_links WHERE expires < :now')
            ->execute(['now' => $now->format('Y-m-d H:i:s')]);
    }
}
