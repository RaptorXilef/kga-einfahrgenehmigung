<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Core\Entity\VerificationRequest;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonVerificationRepository implements VerificationRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function loadPending(): array
    {
        $data = $this->loadJson('pending_verification');
        $now  = new \DateTimeImmutable();

        return \array_filter($data, fn (VerificationRequest $req): bool => ! $req->isExpired($now));
    }

    public function savePending(array $data, bool $forceSql = false): void
    {
        if (! $forceSql) {
            $this->saveJson('pending_verification', $data);
        }
    }

    public function loadVerified(): array
    {
        return $this->loadJson('verified_pending');
    }

    public function saveVerified(array $data, bool $forceSql = false): void
    {
        if (! $forceSql) {
            $this->saveJson('verified_pending', $data);
        }
    }

    public function import(array $data): void
    {
        $objects = [];
        foreach ($data as $token => $row) {
            $exp             = $row['expires'] ?? 'now';
            $dt              = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);
            $objects[$token] = new VerificationRequest((string) $token, $dt, $row['data'] ?? []);
        }
        $this->saveJson('pending_verification', $objects);
    }

    private function loadJson(string $targetKey): array
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $path = $this->config->getStoragePath($cfg['file']);
        $raw  = \file_exists($path) ? JsonHelper::read($path) : [];

        $data = [];
        foreach ($raw as $token => $row) {
            $exp          = $row['expires'] ?? 'now';
            $dt           = \is_numeric($exp) ? (new \DateTimeImmutable())->setTimestamp((int) $exp) : new \DateTimeImmutable($exp);
            $data[$token] = new VerificationRequest((string) $token, $dt, $row['data'] ?? []);
        }

        return $data;
    }

    private function saveJson(string $targetKey, array $requests): void
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $path = $this->config->getStoragePath($cfg['file']);

        $dataToSave = [];
        foreach ($requests as $token => $req) {
            $dataToSave[$token] = [
                'token'   => $req->token,
                'expires' => $req->expiresAt->format('Y-m-d H:i:s'),
                'data'    => $req->data,
            ];
        }
        $this->writeJsonSafely($path, $dataToSave);
    }
}
