<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlMagicLinkRepository implements MagicLinkRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['magic_links'];
        $links = [];
        $stmt  = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $links[$r['token']] = [
                'email'   => $r['email'],
                'code'    => $r['code'],
                'expires' => \is_numeric($r['expires']) ? \date('Y-m-d H:i:s', (int) $r['expires']) : $r['expires'],
            ];
        }

        return $links;
    }

    public function saveAll(array $links, bool $forceSql = false): void
    {
        $cfg = $this->config->get('storage_config')['magic_links'];
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
            $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (token, email, code, expires) VALUES (?, ?, ?, ?)");
            foreach ($links as $token => $d) {
                $exp = $d['expires'] ?? APP_REQUEST_TIME_STR;
                if (\is_numeric($exp)) {
                    $exp = \date('Y-m-d H:i:s', (int) $exp);
                }
                $stmt->execute([$token, $d['email'], $d['code'], $exp]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
