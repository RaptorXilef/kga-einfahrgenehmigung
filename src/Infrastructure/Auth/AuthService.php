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
        // Wenn Session eine System-ID hat oder Dev-Mode aktiv ist -> IMMER TRUE
        $uid      = (string) ($_SESSION['user_id'] ?? '');
        $groupKey = (string) ($_SESSION['admin_group'] ?? 'guest');

        // 1. SYSTEM-BYPASS (Gott-Modus für technische Accounts & Dev-Mode)
        if (\str_starts_with($uid, 'sys_') || $this->config->get('admin_dev_mode')) {
            return true;
        }

        // 2. GRUPPEN-WILDCARD CHECK
        // Wir laden die Gruppe und schauen, ob sie den '*' (Master) direkt hat
        $groups   = $this->loadGroups();
        $groupKey = $_SESSION['admin_group'] ?? 'guest';
        if (isset($groups[$groupKey]['permissions']) && \in_array('*', $groups[$groupKey]['permissions'], true)) {
            return true;
        }

        // 3. COMPILED CHECK (Für spezifische oder negierte Rechte)
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
        // Fix: Pfad muss absolut zum 'public' Ordner sein
        $serverPath = \rtrim($this->config->get('root_path'), '/\\') . '/public/assets/img/' . $folder . '/' . $id . '.webp';

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
        $cfg  = $this->config->get('storage_config')['users'];
        $path = \rtrim($this->config->get('root_path'), '/\\') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];

        return (\file_exists($path) && ! \is_dir($path)) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    public function saveUsers(array $users): void
    {
        $cfg  = $this->config->get('storage_config')['users'];
        $path = \rtrim($this->config->get('root_path'), '/\\') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        \file_put_contents($path, \json_encode($users, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lädt die Gruppen-Definitionen LIVE aus dem konfigurierten Speicher.
     */
    public function loadGroups(): array
    {
        $path = \rtrim($this->config->get('root_path'), '/\\') . '/' . $this->config->get('storage_path_prefix') . 'groups.json';

        return \file_exists($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    public function saveGroups(array $groups): void
    {
        $path = \rtrim($this->config->get('root_path'), '/\\') . '/' . $this->config->get('storage_path_prefix') . 'groups.json';
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
        $targetDir  = \rtrim($this->config->get('root_path'), '/\\') . '/public/assets/img/' . $folder . '/';
        $outputPath = $targetDir . $id . '.webp';

        // 1. Ordner sicherstellen
        if (! \is_dir($targetDir)) {
            \mkdir($targetDir, 0o755, true);
        }

        // FALLBACK: Falls GD nicht installiert ist
        if (! \extension_loaded('gd')) {
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

        // FIX: imagedestroy() entfernt, da GdImage Objekte in PHP 8.0+
        // automatisch bereinigt werden und die Funktion deprecated ist.

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
