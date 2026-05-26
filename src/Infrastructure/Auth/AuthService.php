<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Core\Service\PermissionCompiler;
use App\Infrastructure\Config\Config;

/**
 * Service für die Authentifizierung und Autorisierung von Administratoren.
 *
 * Steuert Login-Validierungen (inklusive Backdoor- und Superadmin-Fallbacks),
 * Session-Management, feingranulare RBAC-Rechteprüfungen und Avatar-/Icon-Bild-Uploads via GD.
 * Kontext: Das primäre Sicherheits-Gateway für alle administrativen UI- und API-Aufrufe.
 *
 * Path: src/Infrastructure/Auth/AuthService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class AuthService
{
    public function __construct(
        private Config $config,
        private ?\PDO $pdo, // Das '?' erlaubt NULL
    ) {
        if (\session_status() !== \PHP_SESSION_NONE) {
            return;
        }

        \session_start();
    }

    /**
     * Verifiziert Anmeldedaten und initialisiert bei Erfolg die Admin-Sitzung.
     * Prüft nacheinander: Inhaber-Backdoor, konfigurierte Superadmin-Credentials und die Benutzer-JSON.
     *
     * @param string $username Der eingegebene Login-Name.
     * @param string $password Das Klartext-Passwort des Benutzers.
     *
     * @return bool True bei erfolgreicher Authentifizierung.
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
            if (
                ($userData['username'] ?? '') === $username
                && \password_verify($password, (string) $userData['pass'])
            ) {
                $this->setSession($userId, (string) $userData['group'], $username);
                $this->refreshSessionPermissions((string) $userData['group']);

                return true;
            }
        }

        return false;
    }

    /**
     * Schreibt Identifikationsmerkmale in die globale $_SESSION Supervariable.
     *
     * @param string $userId  Eindeutige ID (z.B. 'usr_7c13b491' oder 'sys_backdoor').
     * @param string $groupId Die zugehörige Rechtegruppe (z.B. 'grp_71cb1c0d').
     * @param string $label   Der Anzeigename des Benutzers.
     */
    private function setSession(string $userId, string $groupId, string $label): void
    {
        $_SESSION['user_id']     = $userId;
        $_SESSION['admin_user']  = $label;
        $_SESSION['admin_group'] = $groupId;
    }

    /**
     * Die Herzstück-Methode für das Rechtesystem
     * Implementiert Live-Abfrage und strikte "Deny-First" Priorität.
     *
     * Prüft, ob der angemeldete Benutzer eine bestimmte Berechtigung besitzt.
     * Gewährt System-Konten und dem Admin-Dev-Mode sowie Inhabern von '*' generellen Vollzugriff.
     * Alternativ wird die im Session-Cache vorkompilierte Rechte-Map abgefragt.
     *
     * @param string $permission Der gesuchte Berechtigungs-Key (z.B. 'dashboard.logs.view').
     *
     * @return bool True, wenn die Aktion für diesen Benutzer erlaubt ist.
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

    /**
     * Kompiliert die Modul-Rechte für die Gruppe neu und cached sie im aktuellen Session-Scope.
     *
     * @param string $groupId Die ID der Gruppe, deren Berechtigungsbaum kompiliert werden soll.
     */
    public function refreshSessionPermissions(string $groupId): void
    {
        $groups                           = $this->loadGroups();
        $groupPerms                       = $groups[$groupId]['permissions'] ?? [];
        $structure                        = $this->config->get('structure', []);
        $compiler                         = new PermissionCompiler();
        $_SESSION['compiled_permissions'] = $compiler->compile($structure, $groupPerms);
    }

    // --- IDENTITY & MEDIA ---

    /**
     * Gibt den Browser-Pfad zum Profilbild des aktuell angemeldeten Benutzers zurück.
     * public function getProfilePicture(string $username = ''): string
     */
    public function getProfilePicture(): string
    {
        return $this->getImage('user', (string) ($_SESSION['user_id'] ?? 'default'));
    }

    /**
     * Ermittelt den Web-Pfad für Ressourcen (User-Avatare oder Gruppen-Icons) und handhabt Fallbacks.
     * Hängt zur Cache-Busting-Sicherheit den Unix-Zeitstempel der Dateimodifikation als Version an.
     *
     * @param string $type Die Kategorie ('user' oder 'group').
     * @param string $id   Der Dateiname ohne Endung (ID des Objekts).
     *
     * @return string Vollständig qualifizierte URL zum Bild.
     */
    public function getImage(string $type, string $id): string
    {
        $baseUrl  = $this->config->getBaseUrl();
        $isUser   = \str_contains($type, 'user');
        $folder   = $isUser ? 'user_images' : 'group_images';
        $fallback = $isUser ? 'icon-user-default.webp' : 'icon-group-default.webp';

        // Pfad für file_exists (absolut auf dem Server inkl. public/)
        // Fix: Pfad muss absolut zum 'public' Ordner sein
        $serverPath = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/public/assets/img/' . $folder . '/' . $id . '.webp';

        // URL für den Browser (relativ zur baseUrl)
        $browserPath = 'assets/img/' . $folder . '/' . $id . '.webp';

        if (\file_exists($serverPath)) {
            return $baseUrl . $browserPath . '?v=' . \filemtime($serverPath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallback;
    }

    /**
     * Generiert eine pseudo-zufällige, eindeutige alphanumerische ID mit spezifischem Suffix.
     *
     * @param string $prefix Präfix für die ID (z.B. 'usr_' oder 'grp_').
     *
     * @return string Die generierte ID-Zeichenkette.
     */
    public function generateId(string $prefix = ''): string
    {
        return $prefix . \substr(\bin2hex(\random_bytes(4)), 0, 8);
    }

    /**
     * Lädt alle Benutzerkonten aus der konfigurierten JSON-Datenbank.
     *
     * @return array<string, array<string, mixed>> Liste der Benutzer, indiziert nach User-ID.
     */
    public function loadUsers(): array
    {
        $cfg = $this->config->get('storage_config')['users'];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo) {
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

        $path = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];

        return \file_exists($path)
            && ! \is_dir($path) ? (\json_decode((string) \file_get_contents($path), true)
                ?? []) : [];
    }

    /**
     * Überschreibt die Benutzer-JSON-Datei permanent mit dem übergebenen Array.
     *
     * @param array<string, array<string, mixed>> $users Das vollständige Benutzer-Array.
     */
    public function saveUsers(array $users): void
    {
        $cfg = $this->config->get('storage_config')['users'];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo) {
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

            return;
        }

        $path = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];
        \file_put_contents(
            $path,
            \json_encode($users, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Lädt alle Berechtigungsgruppen und Rollen aus Live aus der 'groups.json' bzw. dem konfigurierten Speicher.
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadGroups(): array
    {
        $cfg = $this->config->get('storage_config')['groups'];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo) {
            $stmt   = $this->pdo->query("SELECT * FROM {$cfg['table']}");
            $groups = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $groups[$row['id']] = [
                    'name'        => $row['name'],
                    'permissions' => \json_decode((string) $row['permissions'], true) ?? [],
                ];
            }

            return $groups;
        }

        $path = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];

        return \file_exists($path)
            && ! \is_dir($path) ? (\json_decode((string) \file_get_contents($path), true)
                ?? []) : [];
    }

    /**
     * Persistiert das Gruppen- und Rollen-Array im Dateisystem.
     *
     * @param array<string, array<string, mixed>> $groups
     */
    public function saveGroups(array $groups): void
    {
        $cfg = $this->config->get('storage_config')['groups'];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo) {
            $this->pdo->beginTransaction();

            try {
                $this->pdo->exec("DELETE FROM {$cfg['table']}");
                $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (id, name, permissions) VALUES (?, ?, ?)");
                foreach ($groups as $id => $g) {
                    $stmt->execute([$id, $g['name'], \json_encode($g['permissions'] ?? [])]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }

            return;
        }

        $path = \rtrim(
            $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];
        \file_put_contents($path, \json_encode($groups, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Verarbeitet Bild-Uploads, konvertiert sie in das WebP-Format und skaliert sie transparent via GD.
     * Unterstützt JPEG, PNG, GIF und native WebP-Quellen. Sichert Kompatibilität durch Raw-Move bei
     * fehlender GD-Erweiterung.
     *
     * @param string               $type 'user' oder 'group' zur Verzeichnissteuerung.
     * @param string               $id   Die ID des Ziel-Objekts (wird zum Dateinamen).
     * @param array<string, mixed> $file Das native $_FILES['avatar'] Upload-Array.
     *
     * @return bool True bei erfolgreicher Konvertierung und Speicherung auf dem Datenträger.
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
        return \imagewebp($dst, $outputPath, 75);
    }

    /**
     * Gibt den Anzeigenamen des aktuell angemeldeten Benutzers zurück.
     *
     * @return string Der Name aus $_SESSION oder 'Unbekannt'.
     */
    public function getUsername(): string
    {
        return (string) ($_SESSION['admin_user'] ?? 'Unbekannt');
    }

    /**
     * Gibt die aktive Gruppen-ID des Benutzers zurück.
     *
     * @return string Die ID oder 'guest' bei anonymen Requests.
     */
    public function getGroup(): string
    {
        return (string) ($_SESSION['admin_group'] ?? 'guest');
    }

    /**
     * Abfrage-Schnittstelle zur schnellen Prüfung des Login-Status.
     *
     * @return bool True, wenn eine gültige Admin-Sitzung existiert.
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Zerstört die aktuelle Session vollständig (Logout).
     */
    public function logout(): void
    {
        \session_destroy();
    }

    /**
     * Ruft die eindeutige Benutzer-ID der aktiven Sitzung ab.
     */
    public function getUserId(): string
    {
        return (string) ($_SESSION['user_id'] ?? '');
    }
}
