<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Middleware\AdminAuthGuardMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Core\Service\Maintenance\CronScheduler;
use App\Infrastructure\Maintenance\StorageBootstrapper;

/**
 * Front-Controller für den gesicherten Admin-Bereich.
 * Baut die Middleware-Pipelines und delegiert an die ActionFactory.
 *
 * Path: src/Application/AdminController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class AdminController
{
    /**
     * Initiiert den Controller mit allen Abhängigkeiten (Dependency Injection).
     */
    public function __construct(
        private AdminActionFactory $actionFactory,
        private AdminAuthGuardMiddleware $authGuard,
        private BackupServiceInterface $backupService,
        private CronScheduler $cronScheduler,
        private StorageBootstrapper $bootstrapper,
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
        $pipeline->add(new CsrfMiddleware('admin.php'));

        // Die Login-Logik umgeht natürlich den Guard, alles andere muss durch den Türsteher
        if ($actionKey !== 'login' && $actionKey !== 'logout') {
            $pipeline->add($this->authGuard);
        }

        $pipeline->process(['post' => $post, 'get' => $get], function (array $req) use ($actionKey): void {
            $action = $this->actionFactory->create($actionKey);

            if ($action instanceof ActionInterface) {
                // Mutations-Action
                $message = $action->execute($req['post']);
                \header('Location: admin.php?msg=' . \urlencode($message));
                exit;
            } elseif ($action instanceof ViewActionInterface) {
                // View-Action (wie DashboardRenderAction, AdminPrintAction)
                $action->execute($req);
            } else {
                \header('Location: admin.php');
                exit;
            }
        });
    }
}
