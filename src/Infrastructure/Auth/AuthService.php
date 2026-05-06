<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Infrastructure/Auth/AuthService.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Infrastructure\Config\Config;

/**
 * Service für die Admin-Authentifizierung
 */
final readonly class AuthService
{
    public function __construct(
        private Config $config,
        private ?\PDO $pdo, // Das '?' erlaubt NULL
    ) {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
    }

    /**
     * Prüft Benutzername und Passwort.
     */
    public function login(string $username, string $password): bool
    {
        // 1. Check Hardcoded Superadmin (Level 0)
        $superCfg = $this->config->get('superadmin');
        if ($username === $superCfg['user'] && $password === $superCfg['pass']) {
            $this->setSession($username, 0, 'System-Eigentümer');

            return true;
        }

        // 2. Check JSON Users
        $users = $this->loadUsers();
        if (isset($users[$username]) && \password_verify($password, (string) $users[$username]['pass'])) {
            $this->setSession($username, (int) $users[$username]['level'], (string) ($users[$username]['label'] ?? ''));

            return true;
        }

        return false;
    }

    private function setSession(string $user, int $level, string $label): void
    {
        $_SESSION['admin_user']  = $user;
        $_SESSION['admin_level'] = $level;
        $_SESSION['admin_label'] = $label;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadUsers(): array
    {
        $cfg = $this->config->get('storage_config')['users'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo) {
                throw new \RuntimeException("MySQL-Verbindung für 'Users' erforderlich, aber nicht verfügbar.");
            }
            $stmt  = $this->pdo->query("SELECT * FROM {$cfg['table']}");
            $rows  = $stmt->fetchAll();
            $users = [];
            foreach ($rows as $r) {
                $users[$r['username']] = $r;
            }

            return $users;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    public function saveUsers(array $users): void
    {
        $cfg = $this->config->get('storage_config')['users'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo) {
                throw new \RuntimeException("MySQL-Verbindung für 'Users' erforderlich, aber nicht verfügbar.");
            }
            $stmt = $this->pdo->prepare("REPLACE INTO {$cfg['table']} (username, level, label, pass) VALUES (?, ?, ?, ?)");
            foreach ($users as $username => $data) {
                $stmt->execute(
                    [
                        $username,
                        (int) $data['level'],
                        $data['label'],
                        $data['pass'],
                    ],
                );
            }

            return;
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($users, \JSON_PRETTY_PRINT));
    }

    public function isLoggedIn(): bool
    {
        // NEU: Wenn Dev-Mode aktiv, immer "eingeloggt"
        if ($this->config->get('admin_dev_mode', false) === true) {
            return true;
        }

        return isset($_SESSION['admin_level']);
    }

    public function getLevel(): int
    {
        // Wenn Dev-Mode aktiv, immer Level 0 (Vollzugriff)
        if ($this->config->get('admin_dev_mode', false) === true) {
            return 0; // Im Dev-Mode immer Superadmin
        }

        return (int) ($_SESSION['admin_level'] ?? 3);
    }

    public function logout(): void
    {
        \session_destroy();
    }
}
