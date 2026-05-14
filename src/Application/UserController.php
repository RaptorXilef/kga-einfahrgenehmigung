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
        $action  = $post['action'] ?? '';

        // Routing für Aktionen
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = match ($action) {
                'save_user'    => $this->handleSaveUser($post),
                'delete_user'  => $this->handleDeleteUser($post),
                'save_group'   => $this->handleSaveGroup($post),
                'delete_group' => $this->handleDeleteGroup($post),
                default        => ''
            };

            if ($message !== '') {
                // Redirect um F5-Submit zu verhindern
                \header('Location: users.php?msg=' . \urlencode($message));
                exit;
            }
        }

        $this->render('admin_users', [
            'users'       => $this->auth->loadUsers(),
            'groups'      => $this->auth->loadGroups(),
            'permissions' => $this->config->get('permissions', []),
            'auth'        => $this->auth,
            'message'     => (string) ($_GET['msg'] ?? ''),
            'descOnTop'   => (bool) ($this->config->get('admin_ui')['permissions_desc_on_top'] ?? true),
            'settings'    => [
                'base_url'     => $this->config->getBaseUrl(),
                'vereins_name' => $this->config->get('vereins_name'),
            ],
            'appRoot' => $this->config->get('root_path'), // WICHTIG: Hier für das Template setzen!
        ]);
    }

    private function handleSaveUser(array $post): string
    {
        $users    = $this->auth->loadUsers();
        $username = \trim((string) ($post['username'] ?? ''));
        if ($username === '') {
            return 'Fehler: Benutzername fehlt.';
        }

        $data = $users[$username] ?? [];

        // 1. Gruppe neu zuweisen
        $data['group'] = $post['group'] ?? ($data['group'] ?? 'guest');

        // 2. Bezeichnung
        $data['label'] = \trim((string) ($post['label'] ?? $username));

        // 3. Passwort Reset (nur wenn Feld nicht leer)
        if (! empty($post['password'])) {
            $data['pass'] = \password_hash($post['password'], \PASSWORD_DEFAULT);
            $msgPart      = ' inkl. neuem Passwort';
        } else {
            $msgPart = '';
        }

        $users[$username] = $data;
        $this->auth->saveUsers($users);

        return "Benutzer '$username' wurde gespeichert$msgPart.";
    }

    private function handleDeleteUser(array $post): string
    {
        $username = (string) ($post['username'] ?? '');
        if ($username === $this->auth->getUsername()) {
            return 'Fehler: Selbstausschluss nicht möglich.';
        }

        $users = $this->auth->loadUsers();
        unset($users[$username]);
        $this->auth->saveUsers($users);

        return "Benutzer '$username' wurde entfernt.";
    }

    private function handleSaveGroup(array $post): string
    {
        $groups = $this->auth->loadGroups();
        $id     = \strtolower(\preg_replace('/[^a-z0-9]/', '_', (string) ($post['group_id'] ?? '')));
        if ($id === '') {
            return 'Fehler: Gruppen-ID ungültig.';
        }

        // Permissions aus dem UI verarbeiten (allow, deny, none)
        $rawPerms   = $post['perms'] ?? [];
        $finalPerms = [];

        foreach ($rawPerms as $key => $state) {
            if ($state === 'allow') {
                $finalPerms[] = $key;
            }
            if ($state === 'deny') {
                $finalPerms[] = '-' . $key;
            }
        }

        $groups[$id] = [
            'name'        => \trim((string) ($post['group_name'] ?? $id)),
            'permissions' => $finalPerms,
        ];

        $this->saveGroups($groups);

        return "Gruppe '{$groups[$id]['name']}' wurde aktualisiert.";
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

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data): void
    {
        \extract($data);
        include $this->config->get('root_path') . "/templates/pages/$template.phtml";
    }
}
