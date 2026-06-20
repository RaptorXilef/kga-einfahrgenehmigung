<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * Implementierung des User-Repositories.
 * Regelt den lesenden und schreibenden Zugriff auf die Systemadministratoren,
 * unabhängig davon, ob JSON oder MySQL als Speicher-Backend dient.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserRepository implements UserRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(private ?\PDO $pdo, private ConfigInterface $config)
    {
    }

    /**
     * Lädt alle Benutzerkonten aus der konfigurierten JSON- oder MySQL-Datenbank.
     *
     * @return User[] Liste der Benutzer, indiziert nach User-ID.
     */
    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['users'];
        $users = [];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->query("SELECT * FROM `{$cfg['table']}`");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $users[$row['id']] = new User(
                    $row['id'],
                    $row['username'],
                    $row['group'],
                    $row['pass'],
                );
            }

            return $users;
        }

        $path = $this->config->getStoragePath($cfg['file']);
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

    /**
     * Überschreibt die Benutzer-JSON-Datei oder MySQL-Tabelle permanent mit dem übergebenen Array.
     *
     * @param User[]                              $users
     * @param array<string, array<string, mixed>> $users    Das vollständige Benutzer-Array.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL.
     *
     * @param User[] $users
     */
    public function saveAll(array $users, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['users'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        // 1. In MySQL speichern (Direkt aus den Entities)
        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                $this->pdo->exec("DELETE FROM `{$cfg['table']}`");
                $stmt = $this->pdo->prepare("INSERT INTO `{$cfg['table']}` (id, username, `group`, pass) VALUES (?, ?, ?, ?)");
                foreach ($users as $id => $user) {
                    $stmt->execute([$id, $user->username, $user->groupId, $user->passwordHash]);
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
}
