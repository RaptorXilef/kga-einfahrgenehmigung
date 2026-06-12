<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\AuthService;

/**
 * Controller zur Administration von System-Benutzern, Gruppen und Berechtigungen.
 *
 * Regelt zudem die Profilverwaltung (Avatar-Upload, Passwortänderung) des aktuell eingeloggten Admins.
 * Kontext: Kern-Sicherheitsmodul für Benutzerkonten und Rollenarchitektur.
 *
 * Path: src/Application/UserController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserController
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Haupt-Routing-Handler für Benutzer- und Gruppenmanipulationen.
     * Validiert globale Management-Rechte, fängt POST-Aktionen ab und delegiert an spezifische Worker-Methoden.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $post): void
    {
        if (! $this->auth->hasPermission('system.permissions.view')) {
            \header('Location: admin.php');

            return;
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Globale CSRF-Prüfung für die Benutzerverwaltung
            if (! \hash_equals($_SESSION['csrf_token'] ?? '', $post['csrf_token'] ?? '')) {
                $message = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
            } else {
                $action = $post['action'] ?? '';

                // Wir brauchen eine Variable für die ID, die wir fokussieren wollen
                $focusId = $post['user_id'] ?? ($post['group_id'] ?? '');

                // [x] Sortiert
                $message = match ($action) {
                    'change_user_group', 'change_user_password', 'delete_user', 'rename_user', 'save_user', 'upload_avatar' => $this->auth->hasPermission('system.permissions.users.manage') ? match ($action) {
                        'change_user_group'    => $this->handleChangeUserGroup($post),
                        'change_user_password' => $this->handleResetPassword($post),
                        'delete_user'          => $this->handleDeleteUser($post),
                        'rename_user'          => $this->handleRenameUser($post),
                        'save_user'            => $this->handleSaveUser($post, $_FILES['avatar'] ?? null),
                        'upload_avatar'        => $this->handleUploadAvatar($post, $_FILES['avatar'] ?? null),
                    } : 'Fehler: Keine Berechtigung für die Benutzerverwaltung.',

                    'delete_group', 'rename_group', 'save_group', 'upload_group_image' => $this->auth->hasPermission('system.permissions.groups.manage') ? match ($action) {
                        'delete_group'       => $this->handleDeleteGroup($post),
                        'rename_group'       => $this->handleRenameGroup($post),
                        'save_group'         => $this->handleSaveGroup($post, $_FILES['group_icon'] ?? null),
                        'upload_group_image' => $this->handleUploadGroupImage($post, $_FILES['avatar'] ?? null),
                    } : 'Fehler: Keine Berechtigung für die Gruppenverwaltung.',

                    default => ''
                };
            }

            if ($message !== '') {
                // Wir hängen &focus=... an die URL an
                $redirectUrl = 'users.php?msg=' . \urlencode($message);
                if (isset($focusId) && $focusId !== '') {
                    $redirectUrl .= '&focus=' . \urlencode((string) $focusId);
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

    /**
     * Steuert die Profil-Einstellungsseite des aktuell angemeldeten Benutzers.
     * Erlaubt Eigenmanipulationen von Namen, Passwort und Avatar im aktiven Session-Kontext.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleProfileRequest(array $post): void
    {
        if (! $this->auth->isLoggedIn()) {
            \header('Location: admin.php');

            return;
        }

        $userId  = $_SESSION['user_id'] ?? '';
        $action  = $post['action'] ?? '';
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Globale CSRF-Prüfung für das Eigene Profil
            if (! \hash_equals($_SESSION['csrf_token'] ?? '', $post['csrf_token'] ?? '')) {
                $message = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
            } else {
                // [x] Sortiert
                $message = match ($action) {
                    'change_own_avatar'   => $this->processOwnAvatarUpload($userId, $_FILES['avatar'] ?? null),
                    'change_own_password' => $this->processOwnPasswordChange($userId, $post),
                    'change_own_username' => $this->processOwnUsernameChange($userId, $post),
                    default               => ''
                };
            }

            if ($message !== '') {
                \header('Location: profile.php?msg=' . \urlencode($message));
                exit;
            }
        }

        $users       = $this->auth->loadUsers();
        $groups      = $this->auth->loadGroups();
        $userGroupId = $users[$userId]['group'] ?? 'guest';

        $this->render('profile', [
            'userId'   => $userId,
            'username' => $users[$userId]['username'] ?? 'Unbekannt',
            'group'    => $groups[$userGroupId]['name'] ?? $userGroupId,
            'message'  => (string) ($_GET['msg'] ?? ''),
        ]);
    }

    // START --- Die Match-Worker-Methoden für die Benutzerverwaltung (handleProfileRequest) ---
    // --- USER ACTIONS ---

    /**
     * Erstellt einen neuen Datensatz in der Benutzerverwaltung inklusive Passwort-Hashing.
     *
     * @param array<string, mixed>      $post Formulardaten (username, password, group).
     * @param array<string, mixed>|null $file Optionaler Datei-Array ($_FILES['avatar']).
     *
     * @return string Status- oder Fehlermeldung für die UI.
     */
    private function handleSaveUser(array $post, ?array $file = null): string
    {
        $loginName = \trim((string) ($post['username'] ?? ''));
        $pw1       = (string) ($post['password'] ?? '');
        $pw2       = (string) ($post['password_repeat'] ?? '');

        if ($pw1 !== $pw2) {
            return 'Fehler: Passwörter stimmen nicht überein.';
        }
        if ($pw1 === '' || $pw1 === '0') {
            return 'Fehler: Passwort darf nicht leer sein.';
        }

        $users = $this->auth->loadUsers();

        // Eindeutigkeit des Benutzernamens erzwingen (Verhindert Login-Sperren)
        foreach ($users as $userData) {
            if (\strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($loginName)) {
                return "Fehler: Ein Benutzer mit dem Namen '$loginName' existiert bereits im System.";
            }
        }

        // Solange eine neue ID generieren, bis eine freie gefunden wurde (Kollisionsschutz)
        do {
            $newId = $this->auth->generateId('usr_');
        } while (isset($users[$newId]));

        $users[$newId] = [
            'username' => $loginName,
            'group'    => $post['group'] ?? 'guest',
            'pass'     => \password_hash($pw1, \PASSWORD_DEFAULT),
        ];

        $this->auth->saveUsers($users);

        // Auch hier beim ersten Erstellen die neue ID nutzen!
        if ($file && $file['error'] === 0) {
            $this->auth->uploadImage('user', $newId, $file);
        }

        return "Benutzer '$loginName' erfolgreich erstellt.";
    }

    /**
     * Benennt den Login-Namen eines existierenden Benutzers um.
     *
     * @param array<string, mixed> $post Datensatz mit user_id und new_username.
     *
     * @return string Erfolgs- oder Fehlermeldung.
     */
    private function handleRenameUser(array $post): string
    {
        $userId   = (string) ($post['user_id'] ?? '');
        $newLogin = \trim((string) ($post['new_username'] ?? ''));

        $users = $this->auth->loadUsers();

        // Eindeutigkeit beim Umbenennen prüfen!
        foreach ($users as $id => $userData) {
            if ($id !== $userId && \strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($newLogin)) {
                return "Fehler: Ein Benutzer mit dem Namen '$newLogin' existiert bereits.";
            }
        }

        if (isset($users[$userId])) {
            $users[$userId]['username'] = $newLogin;
            $this->auth->saveUsers($users);

            return 'Login-Name aktualisiert.';
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }

    /**
     * Setzt das Passwort eines Benutzers administrativ (ohne Alt-Passwort-Prüfung) zurück.
     *
     * @param array<string, mixed> $post Datensatz mit user_id und Passwörtern.
     *
     * @return string Ergebnisnachricht.
     */
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

    /**
     * Weist einem Benutzer eine neue Berechtigungsgruppe/Rolle zu.
     *
     * @param array<string, mixed> $post Datensatz mit user_id und group.
     *
     * @return string Bestätigungstext.
     */
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
     * Verarbeitet den Upload und die Skalierung/Speicherung eines Benutzer-Profilbildes.
     *
     * @param array<string, mixed>      $post Das Post-Array mit der Ziel-User-ID.
     * @param array<string, mixed>|null $file Der native $_FILES-Avatar-Eintrag.
     *
     * @return string UI-Meldungstext.
     */
    private function handleUploadAvatar(array $post, ?array $file): string
    {
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }
        // FIX: Wir nutzen jetzt die user_id statt username
        $userId = (string) ($post['user_id'] ?? '');

        return $this->auth->uploadImage('user', $userId, $file)
            ? 'Profilbild aktualisiert.'
            : 'Fehler beim Verarbeiten.';
    }

    /**
     * Löscht einen Benutzer aus dem System. Verhindert den Selbstausschluss des aktiven Admins.
     *
     * @param array<string, mixed> $post Datensatz mit der Ziel-user_id.
     *
     * @return string Erfolgs- oder Fehlermeldung.
     */
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

    // --- GROUP ACTIONS ---

    /**
     * Erstellt eine neue Benutzergruppe oder aktualisiert bestehende Rechte-Zuordnungen.
     * Aktualisiert die Session-Rechte zur Laufzeit, falls die eigene Gruppe modifiziert wurde.
     *
     * @param array<string, mixed>      $post Rechte- und Gruppendaten.
     * @param array<string, mixed>|null $file Optionales Gruppen-Icon ($_FILES).
     *
     * @return string Operations-Ergebnistext.
     */
    private function handleSaveGroup(array $post, ?array $file = null): string
    {
        $groups = $this->auth->loadGroups();

        // 1. Prüfen: Bestehende Gruppe (Update) oder Neue Gruppe (Create)?
        $groupId     = (string) ($post['group_id'] ?? '');
        $isUpdate    = $groupId !== '' && isset($groups[$groupId]);
        $displayName = \trim((string) ($post['group_name'] ?? ''));
        $inheritFrom = (string) ($post['inherit_group'] ?? '');

        // 2. ID bestimmen
        if (! $isUpdate) {
            // Nur bei Neu-Anlage eine neue ID generieren
            // Solange eine neue ID generieren, bis eine freie gefunden wurde (Kollisionsschutz)
            do {
                $groupId = $this->auth->generateId('grp_');
            } while (isset($groups[$groupId]));
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
        // $iconFile = $_FILES['group_icon'] ?? ($_FILES['avatar'] ?? null);
        $iconFile = $file ?? ($_FILES['group_icon'] ?? ($_FILES['avatar'] ?? null));
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

    /**
     * Ändert den Anzeigenamen einer spezifischen Gruppe im System.
     *
     * @param array<string, mixed> $post Datensatz mit group_id und new_group_name.
     *
     * @return string Statusnachricht.
     */
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

    /**
     * Verarbeitet den Upload eines Bildes für Gruppen-Icons.
     *
     * @param array<string, mixed>      $post Das Post-Array mit der group_id.
     * @param array<string, mixed>|null $file Der Datei-Eintrag aus $_FILES.
     *
     * @return string UI-Meldungstext.
     */
    private function handleUploadGroupImage(array $post, ?array $file): string
    {
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }
        $gid = (string) ($post['group_id'] ?? '');

        return $this->auth->uploadImage('group', $gid, $file)
            ? 'Gruppen-Icon aktualisiert.'
            : 'Fehler beim Verarbeiten.';
    }

    /**
     * Löscht eine Gruppe aus dem Berechtigungssystem. Schützt die Kern-Gruppe 'admin'.
     *
     * @param array<string, mixed> $post Datensatz mit group_id.
     *
     * @return string Ergebnisnachricht.
     */
    private function handleDeleteGroup(array $post): string
    {
        $id = (string) ($post['group_id'] ?? '');
        if ($id === 'admin') {
            return 'Fehler: Die Admin-Gruppe kann nicht gelöscht werden.';
        }

        $groups = $this->auth->loadGroups();
        unset($groups[$id]);
        $this->auth->saveGroups($groups);

        return 'Gruppe gelöscht.';
    }

    // ENDE --- Die Match-Worker-Methoden für die Benutzerverwaltung (handleProfileRequest) ---

    // START --- Die Match-Worker-Methoden für das eigene Profil (handleProfileRequest) ---

    /**
     * Aktualisiert den eigenen Anzeigenamen/Login-Namen des angemeldeten Benutzers.
     *
     * @param string               $userId Die aktive Benutzer-ID aus der Session.
     * @param array<string, mixed> $post   Datensatz mit new_username.
     *
     * @return string Ergebnisnachricht.
     */
    private function processOwnUsernameChange(string $userId, array $post): string
    {
        $newName = \trim((string) ($post['new_username'] ?? ''));
        if ($newName === '') {
            return 'Fehler: Name darf nicht leer sein.';
        }

        $users = $this->auth->loadUsers();

        // Eindeutigkeit beim Umbenennen prüfen!
        foreach ($users as $id => $userData) {
            if ($id !== $userId && \strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($newName)) {
                return "Fehler: Der Anzeigename '$newName' ist bereits vergeben.";
            }
        }

        $users[$userId]['username'] = $newName;
        $this->auth->saveUsers($users);

        // Session-Anzeige aktualisieren
        $_SESSION['admin_user'] = $newName;

        return 'Erfolg: Ihr Anzeigename wurde aktualisiert.';
    }

    /**
     * Validiert das alte Kennwort und ändert das Passwort des aktuell angemeldeten Benutzers.
     *
     * @param string               $userId Die aktive Benutzer-ID aus der Session.
     * @param array<string, mixed> $post   Datensatz mit alten und neuen Kennwörtern.
     *
     * @return string Ergebnisnachricht.
     */
    private function processOwnPasswordChange(string $userId, array $post): string
    {
        $oldPass = (string) ($post['old_password'] ?? '');
        $newPass = (string) ($post['new_password'] ?? '');
        $confirm = (string) ($post['confirm_password'] ?? '');

        $users = $this->auth->loadUsers();
        if (! isset($users[$userId]) || ! \password_verify($oldPass, (string) $users[$userId]['pass'])) {
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

    /**
     * Ermöglicht dem aktuell angemeldeten Benutzer das Ändern seines eigenen Profilbildes.
     *
     * @param string                    $userId Die aktive Benutzer-ID aus der Session.
     * @param array<string, mixed>|null $file   Das hochgeladene File-Objekt aus $_FILES.
     *
     * @return string UI-Ergebnistext.
     */
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

    // ENDE --- Die Match-Worker-Methoden für das eigene Profil (handleProfileRequest) ---

    /**
     * Bindet Administrations- und Profil-Templates im User-Kontext ein.
     * Rendert ein Template und stellt sicher, dass alle Pfade und Objekte da sind.
     *
     * @param string               $template Pfadname der Layout-Datei.
     * @param array<string, mixed> $data     Zusatzdaten für den View-Scope.
     */
    private function render(string $template, array $data): void
    {
        $config = $this->config;
        // Sicherstellen, dass appRoot für das Template immer auf einem Slash endet:
        $appRoot = \rtrim((string) $config->get('root_path'), '/\\');

        $templateData = \array_merge([
            'auth'     => $this->auth,
            'config'   => $this->config,
            'appRoot'  => $appRoot . '/',
            'settings' => [
                'base_url'     => $this->config->getBaseUrl(),
                'vereins_name' => $this->config->get('vereins_name'),
            ],
        ], $data);

        \extract($templateData);
        // FIX: Expliziter Schrägstrich vor templates/ hinzugefügt
        include $appRoot . "/templates/pages/{$template}.phtml";
    }
}
