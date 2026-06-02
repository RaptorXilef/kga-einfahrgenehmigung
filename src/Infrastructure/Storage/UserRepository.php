<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * Implementierung des User-Repositories.
 * Regelt den lesenden und schreibenden Zugriff auf die Systemadministratoren,
 * unabhängig davon, ob JSON oder MySQL als Speicher-Backend dient.
 *
 * Path: src/Infrastructure/Storage/UserRepository.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserRepository implements UserRepositoryInterface
{
    use ImageUploadTrait;

    public function __construct(private ?\PDO $pdo, private ConfigInterface $config)
    {
    }

    /**
     * Lädt alle Benutzerkonten aus der konfigurierten JSON- oder MySQL-Datenbank.
     *
     * @return array<string, array<string, mixed>> Liste der Benutzer, indiziert nach User-ID.
     */
    public function loadAll(): array
    {
        $cfg = $this->config->get('storage_config')['users'];
        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt  = $this->pdo->query("SELECT * FROM {$cfg['table']}");
            $users = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $users[$row['id']] = [
                    'username' => $row['username'],
                    'group'    => $row['group'],
                    'pass'     => $row['pass'],
                ];
            }

            return $users;
        }

        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];

        return \file_exists($path) && ! \is_dir($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * Überschreibt die Benutzer-JSON-Datei oder MySQL-Tabelle permanent mit dem übergebenen Array.
     *
     * @param array<string, array<string, mixed>> $users    Das vollständige Benutzer-Array.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL.
     */
    public function saveAll(array $users, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['users'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                // Bei kompletten Array-Updates löschen wir vorher alles, um auch gelöschte Nutzer zu entfernen
                $this->pdo->exec("DELETE FROM {$cfg['table']}");
                // Wichtig: `group` ist in MySQL ein reserviertes Wort und muss in Backticks ` ` gesetzt werden!
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$cfg['table']} (id, username, `group`, pass) VALUES (?, ?, ?, ?)",
                );
                foreach ($users as $id => $u) {
                    $stmt->execute([$id, $u['username'], $u['group'], $u['pass']]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
            // Wenn NUR SQL erzwungen wurde, brechen wir hier ab.
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = \rtrim(
                (string) $this->config->get('root_path'),
                '/\\',
            ) . '/' . \ltrim(
                (string) $this->config->get('storage_path_prefix'),
                '/\\',
            ) . $cfg['file'];
            \file_put_contents(
                $path,
                \json_encode($users, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            );
        }
    }

    /**
     * Lädt einen Avatar für den Benutzer hoch.
     *
     * @param string               $userId Benutzer-ID.
     * @param array<string, mixed> $file   Upload-Daten.
     *
     * @return bool True bei Erfolg.
     */
    public function uploadImage(string $userId, array $file): bool
    {
        return $this->doUploadImage('user_images', $userId, $file, (string) $this->config->get('root_path'));
    }

    /**
     * Ruft die URL zum Avatar des Benutzers ab.
     *
     * @param string $userId Benutzer-ID.
     *
     * @return string Bild-URL.
     */
    public function getImageUrl(string $userId): string
    {
        return $this->doGetImageUrl('user_images', $userId, 'icon-user-default.webp', (string) $this->config->get('root_path'), $this->config->getBaseUrl());
    }
}
