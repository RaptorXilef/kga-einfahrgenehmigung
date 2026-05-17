<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Application/UserController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Auth\AuthService;

/**
 * Orchestriert die Benutzerverwaltung für v0.9.7.
 */
final readonly class UserController
{
    public function __construct(
        private ConfigInterface $config,
        private AuthService $auth,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function handleRequest(array $post): void
    {
        if (! $this->auth->hasPermission('system.users.manage')) {
            \header('Location: admin.php');

            return;
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $post['action'] ?? '';

            // Wir brauchen eine Variable für die ID, die wir fokussieren wollen
            $focusId = $post['user_id'] ?? ($post['group_id'] ?? '');
            $message = match ($action) {
                'save_user'            => $this->handleSaveUser($post, $_FILES['avatar'] ?? null),
                'delete_user'          => $this->handleDeleteUser($post),
                'rename_user'          => $this->handleRenameUser($post),
                'upload_avatar'        => $this->handleUploadAvatar($post, $_FILES['avatar'] ?? null),
                'change_user_group'    => $this->handleChangeUserGroup($post),
                'change_user_password' => $this->handleResetPassword($post),
                'save_group'           => $this->handleSaveGroup($post, $_FILES['group_icon'] ?? null),
                'rename_group'         => $this->handleRenameGroup($post),
                'delete_group'         => $this->handleDeleteGroup($post),
                'upload_group_image'   => $this->handleUploadGroupImage($post, $_FILES['avatar'] ?? null),
                default                => ''
            };

            if ($message !== '') {
                // Wir hängen &focus=... an die URL an
                $redirectUrl = 'users.php?msg=' . \urlencode($message);
                if ($focusId !== '') {
                    $redirectUrl .= '&focus=' . \urlencode($focusId);
                }
                \header('Location: ' . $redirectUrl);
                exit;
            }
        }

        $this->render('admin_users', [
            'users'       => $this->auth->loadUsers(),
            'groups'      => $this->auth->loadGroups(),
            'structure'   => $this->config->get('structure', []),
            'permissions' => $this->config->get('permissions', []),
            'message'     => (string) ($_GET['msg'] ?? ''),
        ]);
    }

    private function handleSaveUser(array $post, ?array $file = null): string
    {
        $loginName = \trim((string) ($post['username'] ?? ''));
        $pw1       = (string) ($post['password'] ?? '');
        $pw2       = (string) ($post['password_repeat'] ?? '');

        if ($pw1 !== $pw2) {
            return 'Fehler: Passwörter stimmen nicht überein.';
        }
        if (empty($pw1)) {
            return 'Fehler: Passwort darf nicht leer sein.';
        }

        $users = $this->auth->loadUsers();
        $newId = $this->auth->generateId('usr_');

        $users[$newId] = [
            'username' => $loginName,
            'group'    => $post['group'] ?? 'guest',
            'pass'     => \password_hash($pw1, \PASSWORD_DEFAULT),
        ];

        $this->auth->saveUsers($users);

        // FIX: Auch hier beim ersten Erstellen die neue ID nutzen!
        if ($file && $file['error'] === 0) {
            $this->auth->uploadImage('user', $newId, $file);
        }

        return "Benutzer '$loginName' erfolgreich erstellt.";
    }

    private function handleSaveGroup(array $post, ?array $file = null): string
    {
        $groups = $this->auth->loadGroups();

        // 1. Prüfen: Bestehende Gruppe (Update) oder Neue Gruppe (Create)?
        $groupId  = (string) ($post['group_id'] ?? '');
        $isUpdate = $groupId !== '' && isset($groups[$groupId]);

        $displayName = \trim((string) ($post['group_name'] ?? ''));
        $inheritFrom = (string) ($post['inherit_group'] ?? '');

        // 2. ID bestimmen
        if (! $isUpdate) {
            // Nur bei Neu-Anlage eine neue ID generieren
            $groupId = $this->auth->generateId('grp_');
        }

        // 3. Berechtigungen verarbeiten
        // Wir nehmen die Rechte aus dem POST-Array (perms[]), falls gesendet
        $newPermissions = (array) ($post['perms'] ?? []);

        // Sonderfall: Wenn wir eine NEUE Gruppe anlegen und "kopieren von" gewählt ist
        if (! $isUpdate && $inheritFrom !== '' && isset($groups[$inheritFrom])) {
            $newPermissions = $groups[$inheritFrom]['permissions'];
        }

        // 4. In das Groups-Array schreiben
        $groups[$groupId] = [
            'name'        => $displayName,
            'permissions' => $newPermissions,
        ];

        $this->auth->saveGroups($groups);

        // 5. Icon-Handling
        // Wir schauen nach 'group_icon' (aus dem Neu-Formular) ODER 'avatar' (aus dem Bearbeiten-Formular)
        $iconFile = $_FILES['group_icon'] ?? ($_FILES['avatar'] ?? null);
        if ($iconFile && $iconFile['error'] === 0) {
            $this->auth->uploadImage('group', $groupId, $iconFile);
        }

        // 6. Rückmeldung
        if ($isUpdate) {
            // WICHTIG: Wenn der aktuell eingeloggte User in dieser Gruppe ist,
            // müssen wir seine Session-Rechte sofort aktualisieren!
            if ($this->auth->getGroup() === $groupId) {
                $this->auth->refreshSessionPermissions($groupId);
            }

            return "Rechte für Gruppe '$displayName' erfolgreich aktualisiert.";
        }

        return "Neue Gruppe '$displayName' wurde erstellt.";
    }

    private function handleRenameUser(array $post): string
    {
        $userId   = (string) ($post['user_id'] ?? '');
        $newLogin = \trim((string) ($post['new_username'] ?? ''));

        $users = $this->auth->loadUsers();
        if (isset($users[$userId])) {
            $users[$userId]['username'] = $newLogin;
            $this->auth->saveUsers($users);

            return 'Login-Name aktualisiert.';
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }

    private function handleResetPassword(array $post): string
    {
        $userId = (string) ($post['user_id'] ?? '');
        $pw1    = (string) ($post['password'] ?? '');
        $pw2    = (string) ($post['password_repeat'] ?? '');

        if ($pw1 !== $pw2) {
            return 'Fehler: Passwörter nicht identisch.';
        }

        $users = $this->auth->loadUsers();
        if (isset($users[$userId])) {
            $users[$userId]['pass'] = \password_hash($pw1, \PASSWORD_DEFAULT);
            $this->auth->saveUsers($users);

            return 'Passwort wurde zurückgesetzt.';
        }

        return 'Fehler.';
    }

    private function handleUploadAvatar(array $post, ?array $file): string
    {
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }
        // FIX: Wir nutzen jetzt die user_id statt username
        $userId = (string) ($post['user_id'] ?? '');

        return $this->auth->uploadImage('user', $userId, $file) ? 'Profilbild aktualisiert.' : 'Fehler beim Verarbeiten.';
    }

    private function handleUploadGroupImage(array $post, ?array $file): string
    {
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }
        $gid = (string) ($post['group_id'] ?? '');

        return $this->auth->uploadImage('group_images', $gid, $file) ? 'Gruppen-Icon aktualisiert.' : 'Fehler beim Verarbeiten.';
    }

    private function handleRenameGroup(array $post): string
    {
        $gid     = (string) ($post['group_id'] ?? '');
        $newName = \trim((string) ($post['new_group_name'] ?? ''));
        if ($gid === '' || $newName === '') {
            return '';
        }

        $groups = $this->auth->loadGroups();
        if (! isset($groups[$gid])) {
            return 'Fehler: Gruppe nicht gefunden.';
        }

        $groups[$gid]['name'] = $newName;
        $this->auth->saveGroups($groups);

        return "Gruppe wurde in '$newName' umbenannt.";
    }

    private function handleDeleteUser(array $post): string
    {
        $userId = (string) ($post['user_id'] ?? '');

        // Selbstausschluss prüfen über die ID aus der Session
        if ($userId === $this->auth->getUserId()) {
            return 'Fehler: Selbstausschluss nicht möglich.';
        }

        $users = $this->auth->loadUsers();
        if (isset($users[$userId])) {
            $name = $users[$userId]['username'] ?? $userId;
            unset($users[$userId]);
            $this->auth->saveUsers($users);

            return "Benutzer '$name' wurde entfernt.";
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }

    private function handleDeleteGroup(array $post): string
    {
        $id = (string) ($post['group_id'] ?? '');
        if ($id === 'admin') {
            return 'Fehler: Die Admin-Gruppe kann nicht gelöscht werden.';
        }

        $groups = $this->auth->loadGroups();
        unset($groups[$id]);
        $this->saveGroups($groups);

        return 'Gruppe gelöscht.';
    }

    private function saveGroups(array $groups): void
    {
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . 'groups.json';
        \file_put_contents($path, \json_encode($groups, \JSON_PRETTY_PRINT));
    }

    private function handleChangeUserGroup(array $post): string
    {
        $userId = (string) ($post['user_id'] ?? ''); // ID aus dem Formular
        $group  = (string) ($post['group'] ?? '');
        $users  = $this->auth->loadUsers();

        if (isset($users[$userId])) {
            $users[$userId]['group'] = $group;
            $this->auth->saveUsers($users);

            return "Gruppe für '" . ($users[$userId]['username'] ?? $userId) . "' geändert.";
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }

    /**
     * Behandelt die Profil-Seite für JEDEN eingeloggten Nutzer.
     *
     * @param array<string, mixed> $post
     */
    public function handleProfileRequest(array $post): void
    {
        if (! $this->auth->isLoggedIn()) {
            \header('Location: admin.php');

            return;
        }

        $message = '';
        $userId  = $_SESSION['user_id'] ?? '';
        $action  = $post['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = match ($action) {
                'change_own_password' => $this->processOwnPasswordChange($userId, $post),
                'change_own_username' => $this->processOwnUsernameChange($userId, $post),
                'change_own_avatar'   => $this->processOwnAvatarUpload($userId, $_FILES['avatar'] ?? null),
                default               => ''
            };
        }

        $users       = $this->auth->loadUsers();
        $groups      = $this->auth->loadGroups();
        $userGroupId = $users[$userId]['group'] ?? 'guest';

        $this->render('profile', [
            'userId'   => $userId,
            'username' => $users[$userId]['username'] ?? 'Unbekannt',
            // Hier den Anzeigenamen der Gruppe holen
            'group'   => $groups[$userGroupId]['name'] ?? $userGroupId,
            'message' => $message,
        ]);
    }

    private function processOwnPasswordChange(string $userId, array $post): string
    {
        $oldPass = (string) ($post['old_password'] ?? '');
        $newPass = (string) ($post['new_password'] ?? '');
        $confirm = (string) ($post['confirm_password'] ?? '');

        $users = $this->auth->loadUsers();
        if (! isset($users[$userId]) || ! \password_verify($oldPass, $users[$userId]['pass'])) {
            return 'Fehler: Das aktuelle Passwort ist nicht korrekt.';
        }
        if ($newPass !== $confirm) {
            return 'Fehler: Die Passwort-Bestätigung stimmt nicht überein.';
        }
        if (\strlen($newPass) < 8) {
            return 'Fehler: Das neue Passwort ist zu kurz.';
        }

        $users[$userId]['pass'] = \password_hash($newPass, \PASSWORD_DEFAULT);
        $this->auth->saveUsers($users);

        return 'Erfolg: Ihr Passwort wurde geändert.';
    }

    private function processOwnUsernameChange(string $userId, array $post): string
    {
        $newName = \trim((string) ($post['new_username'] ?? ''));
        if ($newName === '') {
            return 'Fehler: Name darf nicht leer sein.';
        }

        $users                      = $this->auth->loadUsers();
        $users[$userId]['username'] = $newName;
        $this->auth->saveUsers($users);

        // Session-Anzeige aktualisieren
        $_SESSION['admin_user'] = $newName;

        return 'Erfolg: Ihr Anzeigename wurde aktualisiert.';
    }

    private function processOwnAvatarUpload(string $userId, ?array $file): string
    {
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }

        if ($this->auth->uploadImage('user', $userId, $file)) {
            return 'Erfolg: Profilbild wurde aktualisiert.';
        }

        return 'Fehler bei der Bildverarbeitung.';
    }

    /**
     * Rendert ein Template und stellt sicher, dass alle Pfade und Objekte da sind.
     *
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data): void
    {
        $appRoot      = \rtrim((string) $this->config->get('root_path'), '/\\') . '/';
        $templateData = \array_merge([
            'auth'     => $this->auth,
            'config'   => $this->config,
            'appRoot'  => $appRoot,
            'settings' => [
                'base_url'     => $this->config->getBaseUrl(),
                'vereins_name' => $this->config->get('vereins_name'),
            ],
        ], $data);

        \extract($templateData);
        include $appRoot . "templates/pages/{$template}.phtml";
    }
}
