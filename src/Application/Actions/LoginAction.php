<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Security\CsrfHelper;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action für den Login von Administratoren inkl. Rate-Limiting und CSRF-Schutz.
 *
 * Path: src/Application/Actions/LoginAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class LoginAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $post): string
    {
        // CSRF-Schutz für das Login-Formular
        if (! CsrfHelper::verify($post)) {
            $this->renderForm('Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.');
            exit;
        }

        $user = (string) ($post['user'] ?? '');
        $pass = (string) ($post['pass'] ?? '');

        try {
            if ($this->auth->login($user, $pass)) {
                // Login-Redirects behalten REQUEST-Fokus bei (z.B. für check.php)
                $code = (string) ($_REQUEST['code'] ?? '');
                if ($code !== '') {
                    \header('Location: check.php?code=' . \urlencode($code));
                    exit;
                }

                \header('Location: admin.php');
                exit;
            }

            // Normaler Fehler (Passwort falsch)
            $this->renderForm('Benutzername oder Passwort ist falsch.');
            exit;

        } catch (\RuntimeException $e) {
            // Rate Limit Fehler abfangen
            $this->renderForm($e->getMessage());
            exit;
        }
    }

    /**
     * Hilfsmethode zum erneuten Rendern des Login-Formulars bei Fehlern.
     */
    private function renderForm(string $message): void
    {
        $this->renderer->render('admin_login', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'message'         => $message,
            'userRepository'  => $this->userRepository,
        ]);
    }
}
