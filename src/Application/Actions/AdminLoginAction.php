<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\AdminLoginRequest;
use App\Application\Exception\ValidationException;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action für den Login von Administratoren inkl. Rate-Limiting und CSRF-Schutz.
 *
 * Path: src/Application/Actions/AdminLoginAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class AdminLoginAction implements ActionInterface
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
        try {
            $dto = AdminLoginRequest::fromArray($post);
        } catch (ValidationException $e) {
            $this->renderForm($e->getMessage());
            exit;
        }

        try {
            if ($this->auth->login($dto->username, $dto->password)) {
                // Der Code kommt streng typisiert aus dem DTO!
                if ($dto->redirectCode !== '') {
                    \header('Location: check.php?code=' . \urlencode($dto->redirectCode));
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
