<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\Response\RedirectResponse;
use App\Application\Routing\UniversalActionFactory;
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
        private UniversalActionFactory $factory,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
    ) {
    }

    public function handleRequest(ServerRequest $request): void
    {
        // 1. Die Pipeline für die Benutzerverwaltung definieren
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard);

        // Inline Permission Check statt Middleware
        if (! $this->auth->hasPermission('system.permissions.view')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung.');
            (new RedirectResponse('admin.php'))->send();

            return;
        }

        $pipeline
            ->add(new CsrfMiddleware($this->sessionManager, 'users.php'))
            ->add($this->analyticsMiddleware)
            ->add($this->mailQueueMiddleware);

        $actionKey = $request->post['action'] ?? '';
        $action    = $this->factory->create($actionKey);

        // Dynamisches Routing der Rechte für User- & Group-Actions
        if ($action instanceof RequiresPermissionInterface) {
            if (! $this->auth->hasPermission($action->getRequiredPermission())) {
                $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung.');
                (new RedirectResponse('users.php'))->send();

                return;
            }
        }

        $response = $pipeline->process($request, function (ServerRequest $req) use ($action): mixed {
            if ($req->getMethod() === 'POST' && $action instanceof ActionInterface) {
                return $action->execute($req);
            }

            return $this->factory->create('render_users')->execute($req);
        });

        // STRING FALLBACK ENTFERNT!
        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }

    /**
     * TODO DOCBLOCK
     */
    public function handleProfileRequest(ServerRequest $request): void
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard)
            ->add(new RequireLoginMiddleware($this->auth, 'admin.php'))
            ->add(new CsrfMiddleware($this->sessionManager, 'profile.php'))
            ->add($this->analyticsMiddleware)
            ->add($this->mailQueueMiddleware);

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
        }
    }
}
