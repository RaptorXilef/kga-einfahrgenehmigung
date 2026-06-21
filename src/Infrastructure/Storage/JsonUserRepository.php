<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class JsonUserRepository implements UserRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(private ConfigInterface $config)
    {
    }

    public function loadAll(): array
    {
        $cfg  = $this->config->get('storage_config')['users'];
        $path = $this->config->getStoragePath($cfg['file']);

        $users = [];
        if (! \file_exists($path)) {
            return $users;
        }

        $data = JsonHelper::read($path);
        foreach ($data as $id => $row) {
            $users[$id] = new User(
                $id,
                $row['username'],
                $row['group'],
                $row['pass'],
            );
        }

        return $users;
    }

    public function saveAll(array $users, bool $forceSql = false): void
    {
        // Wenn jemand (z.B. MigrationService) explizit SQL erzwingt, brechen wir hier ab,
        // da dieses Repository keine Datenbank-Verbindung hat.
        if ($forceSql) {
            return;
        }

        $cfg        = $this->config->get('storage_config')['users'];
        $dataToSave = [];

        foreach ($users as $id => $user) {
            $dataToSave[$id] = [
                'username' => $user->username,
                'group'    => $user->groupId,
                'pass'     => $user->passwordHash,
            ];
        }

        $path = $this->config->getStoragePath($cfg['file']);
        $this->writeJsonSafely($path, $dataToSave);
    }
}
