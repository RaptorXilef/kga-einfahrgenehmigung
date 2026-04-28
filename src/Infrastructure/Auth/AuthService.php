<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Infrastructure\Config\Config;

/**
 * Service für die Admin-Authentifizierung (v0.6.0).
 */
final readonly class AuthService
{
    public function __construct(
        private Config $config,
    ) {
        if (\session_status() !== \PHP_SESSION_NONE) {
            return;
        }

        \session_start();
    }

    /**
     * Prüft Benutzername und Passwort.
     */
    public function login(string $username, string $password): bool
    {
        $usersPath = $this->config->get('root_path') . '/storage/users.json';
        if (! \file_exists($usersPath)) {
            return false;
        }

        $users = \json_decode(\file_get_contents($usersPath), true) ?? [];

        if (isset($users[$username]) && \password_verify($password, (string) $users[$username]['pass'])) {
            $_SESSION['admin_user']  = $username;
            $_SESSION['admin_level'] = $users[$username]['level'];

            return true;
        }

        return false;
    }

    public function logout(): void
    {
        unset($_SESSION['admin_user'], $_SESSION['admin_level']);
        \session_destroy();
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
        // NEU: Wenn Dev-Mode aktiv, immer Level 1 (Vollzugriff)
        if ($this->config->get('admin_dev_mode', false) === true) {
            return 1;
        }

        return (int) ($_SESSION['admin_level'] ?? 0);
    }
}
