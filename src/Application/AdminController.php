<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AdminAuthGuardMiddleware;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\MigrationPermissionMiddleware;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\PrintAuthorizationMiddleware;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\SystemMaintenanceMiddleware;
use App\Application\Middleware\ToggleSuspensionMiddleware;
use App\Application\Middleware\VoucherIssuanceMiddleware;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ResponseInterface;
use App\Contracts\Application\ViewActionInterface;
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
        private AdminActionFactory $actionFactory,
        private AdminAuthGuardMiddleware $authGuard,
        private AnalyticsMiddleware $analyticsMiddleware,
        private AuthService $auth,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private SystemMaintenanceMiddleware $maintenanceMiddleware,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
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
        if ($actionKey !== 'login' && $actionKey !== 'logout') {
            $pipeline->add($this->authGuard);
        }

        // Dynamisches Routing! Die Action entscheidet selbst, welche Rechte sie braucht.
        if ($action instanceof RequiresPermissionInterface) {
            $pipeline->add(new PermissionMiddleware(
                $this->auth,
                $action->getRequiredPermission(),
                'admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung.'),
            ));
        }

        // Komplexe Spezial-Middlewares
        if ($actionKey === 'migrate_data') {
            $pipeline->add(new MigrationPermissionMiddleware($this->auth));
        }
        if ($actionKey === 'resend_mail') {
            $pipeline->add(new PermissionMiddleware($this->auth, 'dashboard.logs.view', 'admin.php'));
            $pipeline->add(new PermissionMiddleware($this->auth, 'dashboard.generator-tools.direct_issue.execute', 'admin.php'));
        }
        if ($actionKey === 'admin_print') {
            $pipeline->add(new PrintAuthorizationMiddleware($this->auth, $this->storage));
        }
        if ($actionKey === 'suspend_permit' || $actionKey === 'unsuspend_permit') {
            $pipeline->add(new ToggleSuspensionMiddleware($this->auth, $this->storage));
        }
        if ($actionKey === 'create_voucher') {
            $pipeline->add(new VoucherIssuanceMiddleware($this->auth));
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
