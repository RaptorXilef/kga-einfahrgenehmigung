<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\MigrationPermissionMiddleware;
use App\Application\Middleware\PrintAuthorizationMiddleware;
use App\Application\Middleware\RequireLoginMiddleware;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\SystemMaintenanceMiddleware;
use App\Application\Middleware\ToggleSuspensionMiddleware;
use App\Application\Middleware\VoucherIssuanceMiddleware;
use App\Application\Response\RedirectResponse;
use App\Application\Routing\UniversalActionFactory;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\AuthService;

/**
 * Front-Controller für den gesicherten Admin-Bereich.
 * Baut die Middleware-Pipelines und delegiert an die ActionFactory.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AdminController
{
    /**
     * Initiiert den Controller mit allen Abhängigkeiten (Dependency Injection).
     */
    public function __construct(
        private AnalyticsMiddleware $analyticsMiddleware,
        private AuthService $auth,
        private MaintenanceGuardMiddleware $maintenanceGuard,
        private RequireLoginMiddleware $authGuard,
        private SecurityHeadersMiddleware $securityHeaders,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private SystemMaintenanceMiddleware $maintenanceMiddleware,
        private UniversalActionFactory $actionFactory,
    ) {
    }

    /**
     * Haupt-Request-Handler für Admin-Routen.
     *
     * Steuert Authentifizierung, System-Initialisierung und Weiterleitung.
     * Orchestriert: Authentifizierung -> Wartungs-Checks -> POST-Aktionen -> Rendering.
     */
    public function handleRequest(ServerRequest $request): void
    {
        $actionKey = (string) ($request->post['action'] ?? ($request->get['action'] ?? 'render_dashboard'));

        // Export & Print als ViewActions abfangen
        if (isset($request->get['export'])) {
            $actionKey = 'dashboard_export';
        }
        if ($actionKey === 'print') {
            $actionKey = 'admin_print';
        }

        if (isset($request->files['bank_csv'])) {
            $actionKey = 'bank_import_analyze';
        }

        // Mache Keys global eindeutig für den Universal-Router
        if ($actionKey === 'login') {
            $actionKey = 'admin_login';
        }
        if ($actionKey === 'logout') {
            $actionKey = 'admin_logout';
        }

        // Action VOR der Pipeline instanziieren
        $action = $this->actionFactory->create($actionKey);

        // Pipeline aufbauen
        $pipeline = new MiddlewarePipeline();
        // Die System-Wartung läuft nun sicher über die Middleware!
        $pipeline
            ->add($this->securityHeaders)
            ->add($this->maintenanceGuard)
            ->add($this->maintenanceMiddleware)
            ->add($this->analyticsMiddleware)
            ->add(new CsrfMiddleware($this->sessionManager, 'admin.php'));

        // Die Login-Logik umgeht natürlich den Guard, alles andere muss durch den Türsteher
        if ($actionKey !== 'admin_login' && $actionKey !== 'admin_logout') {
            $pipeline->add($this->authGuard);
        }

        // Dynamisches Routing! Die Action entscheidet selbst, welche Rechte sie braucht.
        if ($action instanceof RequiresPermissionInterface) {
            if (! $this->auth->hasPermission($action->getRequiredPermission())) {
                $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung.');
                (new RedirectResponse('admin.php'))->send();

                return;
            }
        }

        // Komplexe Spezial-Middlewares
        if ($actionKey === 'migrate_data') {
            $pipeline->add(new MigrationPermissionMiddleware($this->auth, $this->sessionManager));
        }
        if ($actionKey === 'resend_mail') {
            // Vereinfachter Permission Check für Middlewares ohne URL-Parameter
            if (! $this->auth->hasPermission('dashboard.logs.view') || ! $this->auth->hasPermission('dashboard.generator-tools.direct_issue.execute')) {
                $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung.');
                (new RedirectResponse('admin.php'))->send();

                return;
            }
        }
        if ($actionKey === 'admin_print') {
            $pipeline->add(new PrintAuthorizationMiddleware($this->auth, $this->sessionManager, $this->storage));
        }
        if ($actionKey === 'suspend_permit' || $actionKey === 'unsuspend_permit') {
            $pipeline->add(new ToggleSuspensionMiddleware($this->auth, $this->sessionManager, $this->storage));
        }
        if ($actionKey === 'create_voucher') {
            $pipeline->add(new VoucherIssuanceMiddleware($this->auth, $this->sessionManager));
        }

        $response = $pipeline->process($request, function (ServerRequest $req) use ($action): mixed {
            if ($action instanceof ActionInterface || $action instanceof ViewActionInterface) {
                return $action->execute($req);
            }

            return new RedirectResponse('admin.php');
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
