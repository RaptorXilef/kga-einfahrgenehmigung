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
 */
final readonly class GroupRepository implements GroupRepositoryInterface
{
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
}
