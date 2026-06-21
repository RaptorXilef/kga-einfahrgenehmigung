<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AdminAuthGuardMiddleware;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\MigrationPermissionMiddleware;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\PrintAuthorizationMiddleware;
use App\Application\Middleware\ToggleSuspensionMiddleware;
use App\Application\Middleware\VoucherIssuanceMiddleware;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\ResponseInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\AuthService;
use App\Core\Service\Maintenance\CronScheduler;
use App\Infrastructure\Maintenance\StorageBootstrapper;

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
        private BackupServiceInterface $backupService,
        private CronScheduler $cronScheduler,
        private SessionManager $sessionManager,
        private StorageBootstrapper $bootstrapper,
        private StorageInterface $storage,
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
        // 1. SYSTEM-INITIALISIERUNG
        try {
            // Hier rufen ich jetzt NUR noch den sauberen Bootstrapper auf
            $this->bootstrapper->bootstrap();

            // Orchestriert Backup & Archivierung über Pseudo-Cron
            $this->cronScheduler->runIfNeeded();

            // Cronjob für automatische Backups darf bleiben
            $this->backupService->checkAutoBackup();
        } catch (\Throwable $e) {
            // Fängt Fehler ab, damit das Dashboard nicht abstürzt
            \error_log('Bootstrapping Warning: ' . $e->getMessage());
        }

        // Action-Key ermitteln
        $actionKey = (string) ($request->post['action'] ?? ($request->get['action'] ?? 'render_dashboard'));

        // Export & Print als ViewActions abfangen
        if (isset($request->get['export'])) {
            $actionKey = 'dashboard_export';
        }
        if ($actionKey === 'print') {
            $actionKey = 'admin_print';
        }

        // Pipeline aufbauen
        $pipeline = new MiddlewarePipeline();
        $pipeline->add($this->analyticsMiddleware);
        $pipeline->add(new CsrfMiddleware($this->sessionManager, 'admin.php'));

        // Die Login-Logik umgeht natürlich den Guard, alles andere muss durch den Türsteher
        if ($actionKey !== 'login' && $actionKey !== 'logout') {
            $pipeline->add($this->authGuard);
        }

        $permissionMap = [
            'activate_voucher'      => 'dashboard.vouchers.suspend',
            'anonymize_archive'     => 'dashboard.migration.anonymize.execute',
            'clear_cache'           => 'dashboard.migration.delete-cache.execute',
            'create_backup'         => 'dashboard.migration.view',
            'create_manual'         => 'dashboard.generator-tools.manual_permit.execute',
            'dashboard_export'      => 'finance.export.execute',
            'deactivate_voucher'    => 'dashboard.vouchers.suspend',
            'delete_voucher'        => 'dashboard.vouchers.remove',
            'mark_as_paid'          => 'dashboard.finance.mark_paid',
            'restore_data'          => 'dashboard.migration.restore.execute',
            'run_update_migrations' => 'system.update.execute',
            'truncate_target'       => 'dashboard.migration.delete-data.execute',
        ];

        if (isset($permissionMap[$actionKey])) {
            $pipeline->add(new PermissionMiddleware($this->auth, $permissionMap[$actionKey], 'admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung.')));
        }

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

        $response = $pipeline->process($request, function (ServerRequest $req) use ($actionKey): mixed {
            $action = $this->actionFactory->create($actionKey);
            if ($action instanceof ActionInterface) {
                return $action->execute($req);
            } elseif ($action instanceof ViewActionInterface) {
                return $action->execute($req);
            }

            return new RedirectResponse('admin.php');
        });

        // POLYMORPHISMUS PUR: Alle Hard-Exits wurden in die Interface-Send Methode verlagert!
        if ($response instanceof ResponseInterface) {
            $response->send();
        } elseif (\is_string($response)) {
            (new RedirectResponse('admin.php?msg=' . \urlencode($response)))->send();
        }
    }
}
