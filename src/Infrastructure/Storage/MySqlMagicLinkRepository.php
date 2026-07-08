<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Core\Entity\MagicLink;
use App\Core\ValueObject\EmailAddress;

/**
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlMagicLinkRepository implements MagicLinkRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['magic_links'];
        $links = [];

        $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $exp                = $r['expires'];
            $dt                 = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);
            $links[$r['token']] = new MagicLink(
                $r['token'],
                new EmailAddress($r['email']),
                $r['code'],
                $dt,
            );
        }

        return $links;
    }

    /**
     * @param MagicLink[] $links
     */
    public function saveAll(array $links, bool $forceSql = false): void
    {
        $table = $this->config->get('storage_config')['magic_links']['table'];
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$table}`");
            $sql  = null;
            $stmt = null;

            foreach ($links as $token => $link) {
                $data = [
                    'token'   => $token,
                    'email'   => $link->email->value,
                    'code'    => $link->code,
                    'expires' => $link->expires->format('Y-m-d H:i:s'),
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

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $token => $row) {
            $exp = $row['expires'] ?? 'now';
            $dt  = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);

            $objects[$token] = new MagicLink(
                (string) $token,
                new EmailAddress($row['email'] ?? 'invalid@example.com'),
                $row['code'] ?? '',
                $dt,
            );
        }
        $this->saveAll($objects, true);
    }
}
