<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\MagicLink;
use App\Core\ValueObject\EmailAddress;

/**
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonMagicLinkRepository implements MagicLinkRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['magic_links'];
        $path  = $this->config->getStoragePath($cfg['file']);
        $raw   = \file_exists($path) ? $this->jsonHelper->read($path) : [];
        $links = [];

        foreach ($raw as $token => $l) {
            $exp           = $l['expires'] ?? 'now';
            $dt            = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);
            $links[$token] = new MagicLink(
                (string) $token,
                new EmailAddress($l['email'] ?? 'invalid@example.com'),
                $l['code'] ?? '',
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
        if ($forceSql) {
            return;
        }

        $cfg  = $this->config->get('storage_config')['magic_links'];
        $path = $this->config->getStoragePath($cfg['file']);
        $data = [];

        foreach ($links as $token => $link) {
            $data[$token] = [
                'email'   => $link->email->value,
                'code'    => $link->code,
                'expires' => $link->expires->format('Y-m-d H:i:s'),
            ];
        }

        $this->writeJsonSafely($path, $data, \JSON_PRETTY_PRINT);
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
        $this->saveAll($objects);
    }
}
