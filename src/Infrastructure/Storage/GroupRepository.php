<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Entity\Group;

/**
 * Implementierung des Gruppen-Repositories.
 * Verwaltet Berechtigungsrollen und deren Icons. Erleichtert die Migration
 * zwischen den Speicher-Engines (JSON/MySQL).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class GroupRepository implements GroupRepositoryInterface
{
    use ImageUploadTrait;
    use SafeJsonWriterTrait;

    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Lädt alle Berechtigungsgruppen und Rollen aus Live aus der 'groups.json' bzw. dem konfigurierten Speicher.
     *
     * @return Group[]
     */
    public function loadAll(): array
    {
        $cfg    = $this->config->get('storage_config')['groups'];
        $groups = [];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $perms = \is_string($row['permissions'])
                    ? JsonHelper::decode($row['permissions'])
                    : $row['permissions'];
                $groups[$row['id']] = new Group(
                    $row['id'],
                    $row['name'],
                    $perms ?? [],
                );
            }

            return $groups;
        }

        $path = $this->config->getStoragePath($cfg['file']);
        $data = JsonHelper::read($path);
        foreach ($data as $id => $row) {
            $groups[$id] = new Group(
                $id,
                $row['name'],
                $row['permissions'] ?? [],
            );
        }

        return $groups;
    }

    /**
     * Persistiert das Gruppen- und Rollen-Array im Dateisystem.
     *
     * @param Group[]                             $groups
     * @param array<string, array<string, mixed>> $groups
     */
    public function saveAll(array $groups, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['groups'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        // 1. In MySQL speichern (Direkt aus den Entities)
        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
                $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (id, name, permissions) VALUES (?, ?, ?)");
                foreach ($groups as $id => $group) {
                    $stmt->execute([$id, $group->name, \json_encode(
                        $group->permissions,
                        \JSON_UNESCAPED_UNICODE,
                    )]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
            if ($forceSql) {
                return;
            }
        }

        // 2. In JSON speichern (Entities wieder zu flachen Arrays umwandeln)
        if (! $forceSql) {
            $dataToSave = [];
            foreach ($groups as $id => $group) {
                $dataToSave[$id] = [
                    'name'        => $group->name,
                    'permissions' => $group->permissions,
                ];
            }
            $path = $this->config->getStoragePath($cfg['file']);
            $this->writeJsonSafely($path, $dataToSave);
        }
    }

    /**
     * Lädt das Gruppen-Icon hoch und konvertiert es.
     *
     * @param string               $groupId Die ID der Gruppe.
     * @param array<string, mixed> $file    Upload-Daten.
     *
     * @return bool True bei Erfolg.
     */
    public function uploadImage(string $groupId, array $file): bool
    {
        return $this->doUploadImage('group_images', $groupId, $file, (string) $this->config->get('root_path'));
    }

    /**
     * Gibt die URL des Gruppen-Icons zurück. Fällt auf ein Standard-Icon zurück, falls keines existiert.
     *
     * @param string $groupId Die ID der Gruppe.
     *
     * @return string URL zum Bild.
     */
    public function getImageUrl(string $groupId): string
    {
        return $this->doGetImageUrl(
            'group_images',
            $groupId,
            'icon-group-default.webp',
            (string) $this->config->get('root_path'),
            $this->config->getBaseUrl(),
        );
    }
}
