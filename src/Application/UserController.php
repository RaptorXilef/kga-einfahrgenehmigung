<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\UserActionFactory;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;

/**
 * Front Controller zur Administration von Benutzern, Gruppen und Profilen.
 *
 * Sichert die Routen über die Middleware-Pipeline ab und delegiert
 * die Logik an spezialisierte Action-Klassen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserController
{
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private AuthService $auth,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private UserActionFactory $factory,
    ) {
    }

    public function handleRequest(array $post, array $get): void
    {
        // 1. Die Pipeline für die Benutzerverwaltung definieren
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new PermissionMiddleware($this->auth, 'system.permissions.view', 'admin.php'));
        $pipeline->add(new CsrfMiddleware('users.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        // 2. Den Request durch die Pipeline schicken
        $pipeline->process(['post' => $post, 'get' => $get], function (array $req): void {
            $post = $req['post'];
            $get  = $req['get'];

            // Ab hier wissen wir zu 100%: Der Nutzer hat Rechte und das CSRF-Token stimmt!
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $actionKey = $post['action'] ?? '';
                $focusId   = $post['user_id'] ?? ($post['group_id'] ?? '');
                $action    = $this->factory->create($actionKey);

                if ($action instanceof ActionInterface) {
                    $result = $action->execute($post);

                    // NEU: RedirectResponse abfangen
                    if ($result instanceof RedirectResponse) {
                        $result->send();
                    } else {
                        // Fallback für alte String-Meldungen
                        $redirectUrl = 'users.php?msg=' . \urlencode((string) $result);
                        if ($focusId !== '') {
                            $redirectUrl .= '&focus=' . \urlencode((string) $focusId);
                        }
                        \header('Location: ' . $redirectUrl);
                        exit;
                    }
                }
            }

            $renderAction = $this->factory->create('render_users');
            $result       = $renderAction->execute(['get' => $get]);
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }

    /**
     * TODO DOCBLOCK
     */
    public function handleProfileRequest(array $post, array $get): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new RequireLoginMiddleware($this->auth, 'admin.php'));
        $pipeline->add(new CsrfMiddleware('profile.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $pipeline->process(['post' => $post, 'get' => $get], function (array $req): void {
            $post = $req['post'];
            $get  = $req['get'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $actionKey = $post['action'] ?? '';
                $action    = $this->factory->create($actionKey);

                if ($action instanceof ActionInterface) {
                    $result = $action->execute($post);

                    // RedirectResponse abfangen
                    if ($result instanceof RedirectResponse) {
                        $result->send();
                    } else {
                        \header('Location: profile.php?msg=' . \urlencode((string) $result));
                        exit;
                    }
                }
            }

            $renderAction = $this->factory->create('render_profile');
            $result       = $renderAction->execute(['get' => $get]);
            if ($result instanceof RedirectResponse) {
                $result->send();
            }
        });
    }
}
