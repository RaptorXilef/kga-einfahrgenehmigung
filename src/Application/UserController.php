<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\UserActionFactory;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ResponseInterface;
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
        private SessionManager $sessionManager,
        private TerminateMailQueueMiddleware $mailQueueMiddleware,
        private UserActionFactory $factory,
    ) {
    }

    public function handleRequest(ServerRequest $request): void
    {
        // 1. Die Pipeline für die Benutzerverwaltung definieren
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new PermissionMiddleware($this->auth, 'system.permissions.view', 'admin.php'));
        $pipeline->add(new CsrfMiddleware($this->sessionManager, 'users.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $actionKey = $request->post['action'] ?? '';
        $action    = $this->factory->create($actionKey);

        // Dynamisches Routing der Rechte für User- & Group-Actions
        if ($action instanceof RequiresPermissionInterface) {
            $pipeline->add(new PermissionMiddleware(
                $this->auth,
                $action->getRequiredPermission(),
                'users.php?msg=' . \urlencode('Fehler: Keine Berechtigung.'),
            ));
        }

        $response = $pipeline->process($request, function (ServerRequest $req) use ($action): mixed {
            if ($req->getMethod() === 'POST' && $action instanceof ActionInterface) {
                return $action->execute($req);
            }

            return $this->factory->create('render_users')->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        } elseif (\is_string($response)) {
            $focusId = $request->post['user_id'] ?? ($request->post['group_id'] ?? '');
            $url     = 'users.php?msg=' . \urlencode($response) . ($focusId !== '' ? '&focus=' . \urlencode($focusId) : '');
            (new RedirectResponse($url))->send();
        }
    }

    /**
     * TODO DOCBLOCK
     */
    public function handleProfileRequest(ServerRequest $request): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new RequireLoginMiddleware($this->auth, 'admin.php'));
        $pipeline->add(new CsrfMiddleware($this->sessionManager, 'profile.php'));
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add($this->mailQueueMiddleware);

        $response = $pipeline->process($request, function (ServerRequest $req): mixed {
            if ($req->getMethod() === 'POST') {
                $action = $this->factory->create($req->post['action'] ?? '');
                if ($action instanceof ActionInterface) {
                    return $action->execute($req);
                }
            }

            return $this->factory->create('render_profile')->execute($req);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        } elseif (\is_string($response)) {
            (new RedirectResponse('profile.php?msg=' . \urlencode($response)))->send();
        }
    }
}
