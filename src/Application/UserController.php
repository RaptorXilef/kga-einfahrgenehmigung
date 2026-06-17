<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\UserActionFactory;
use App\Application\Security\CsrfHelper;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;

/**
 * Front Controller zur Administration von Benutzern, Gruppen und Profilen.
 *
 * Sichert die Routen durch Berechtigungsprüfungen und CSRF-Schutz ab
 * und delegiert die Logik an spezialisierte Action-Klassen über die UserActionFactory.
 * Behält das PRG-Pattern bei.
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
        private UserActionFactory $factory,
        private AuthService $auth,
    ) {
    }

    // TODO DOCBLOCK
    public function handleRequest(array $post, array $get): void
    {
        if (! $this->auth->hasPermission('system.permissions.view')) {
            \header('Location: admin.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Globale CSRF-Prüfung für die Benutzerverwaltung
            if (! CsrfHelper::verify($post)) {
                $msg = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                \header('Location: users.php?msg=' . \urlencode($msg));
                exit;
            }

            $actionKey = $post['action'] ?? '';

            // Wir brauchen eine Variable für die ID, die wir fokussieren wollen
            $focusId = $post['user_id'] ?? ($post['group_id'] ?? '');

            $action = $this->factory->create($actionKey);
            if ($action instanceof ActionInterface) {
                $msg = $action->execute($post);

                $redirectUrl = 'users.php?msg=' . \urlencode($msg);
                if ($focusId !== '') {
                    $redirectUrl .= '&focus=' . \urlencode((string) $focusId);
                }
                \header('Location: ' . $redirectUrl);
                exit;
            }
        }

        $this->factory->create('render_users')->execute(['get' => $get]);
    }

    /**
     * TODO DOCBLOCK
     */
    public function handleProfileRequest(array $post, array $get): void
    {
        if (! $this->auth->isLoggedIn()) {
            \header('Location: admin.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Globale CSRF-Prüfung für das Eigene Profil
            if (! CsrfHelper::verify($post)) {
                $msg = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                \header('Location: profile.php?msg=' . \urlencode($msg));
                exit;
            }

            $actionKey = $post['action'] ?? '';
            $action    = $this->factory->create($actionKey);

            if ($action instanceof ActionInterface) {
                $msg = $action->execute($post);
                \header('Location: profile.php?msg=' . \urlencode($msg));
                exit;
            }
        }

        $this->factory->create('render_profile')->execute(['get' => $get]);
    }
}
