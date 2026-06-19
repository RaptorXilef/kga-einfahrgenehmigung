<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Middleware\AdminAuthGuardMiddleware;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Middleware\PrintAuthorizationMiddleware;
use App\Application\Middleware\ToggleSuspensionMiddleware;
use App\Application\Middleware\VoucherIssuanceMiddleware;
use App\Contracts\Application\ActionInterface;
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
        private StorageBootstrapper $bootstrapper,
        private StorageInterface $storage,
    ) {
    }

    /**
     * Haupt-Request-Handler für Admin-Routen.
     *
     * Steuert Authentifizierung, System-Initialisierung und Weiterleitung.
     * Orchestriert: Authentifizierung -> Wartungs-Checks -> POST-Aktionen -> Rendering.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
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
        $actionKey = (string) ($post['action'] ?? ($get['action'] ?? 'render_dashboard'));

        // Export & Print als ViewActions abfangen
        if (isset($get['export'])) {
            $actionKey = 'dashboard_export';
        }
        if ($actionKey === 'print') {
            $actionKey = 'admin_print';
        }

        // Pipeline aufbauen
        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->analyticsMiddleware)
            ->add(new CsrfMiddleware('admin.php'));

        // Die Login-Logik umgeht natürlich den Guard, alles andere muss durch den Türsteher
        if ($actionKey !== 'login' && $actionKey !== 'logout') {
            $pipeline->add($this->authGuard);
        }
        if ($actionKey === 'dashboard_export') {
            $pipeline->add(new PermissionMiddleware($this->auth, 'finance.export.execute', 'admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung für Exporte.')));
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

        $pipeline->process(['post' => $post, 'get' => $get], function (array $req) use ($actionKey): void {
            $action = $this->actionFactory->create($actionKey);

            if ($action instanceof ActionInterface) {
                $result = $action->execute($req['post']);

                // Wenn die Action einen Redirect auslöst, führe ihn hier im HTTP-Layer aus!
                if ($result instanceof Response\RedirectResponse) {
                    $result->send();
                } else {
                    // Fallback für alte Actions, die nur einen String (Nachricht) zurückgeben
                    \header('Location: admin.php?msg=' . \urlencode($result));
                    exit;
                }
            } elseif ($action instanceof ViewActionInterface) {
                // View-Action (wie DashboardRenderAction, AdminPrintAction)
                $result = $action->execute($req);
                if ($result instanceof Response\RedirectResponse) {
                    $result->send();
                }
            } else {
                \header('Location: admin.php');
                exit;
            }
        });
    }
}
