<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\UserActionFactory;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;

/**
 * Front Controller zur Administration von Benutzern, Gruppen und Profilen.
 *
 * Sichert die Routen über die Middleware-Pipeline ab und delegiert
 * die Logik an spezialisierte Action-Klassen.
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
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private UserActionFactory $factory,
    ) {
    }

    // TODO DOCBLOCK
    public function handleRequest(array $post, array $get): void
    {
        // 1. Die Pipeline für die Benutzerverwaltung definieren
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add(new PermissionMiddleware($this->auth, 'system.permissions.view', 'admin.php'))
            ->add(new CsrfMiddleware('users.php'))
            ->add($this->mailQueueMiddleware);

        // 2. Den Request durch die Pipeline schicken
        $pipeline->process(['post' => $post, 'get' => $get], function (array $req): void {
            $post = $req['post'];
            $get  = $req['get'];

            // Ab hier wissen wir zu 100%: Der Nutzer hat Rechte und das CSRF-Token stimmt!
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        });
    }

    /**
     * TODO DOCBLOCK
     */
    public function handleProfileRequest(array $post, array $get): void
    {
        // 1. Die Pipeline für das eigene Profil definieren
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add(new RequireLoginMiddleware($this->auth, 'admin.php'))
            ->add(new CsrfMiddleware('profile.php'));

        // 2. Den Request durch die Pipeline schicken
        $pipeline->process(['post' => $post, 'get' => $get], function (array $req): void {
            $post = $req['post'];
            $get  = $req['get'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $actionKey = $post['action'] ?? '';
                $action    = $this->factory->create($actionKey);

                if ($action instanceof ActionInterface) {
                    $msg = $action->execute($post);
                    \header('Location: profile.php?msg=' . \urlencode($msg));
                    exit;
                }
            }

            $this->factory->create('render_profile')->execute(['get' => $get]);
        });
    }
}
