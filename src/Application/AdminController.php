<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\ExportService;
use App\Core\Service\Maintenance\CronScheduler;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\StorageBootstrapper;

/**
 * Haupt-Controller für die Administration.
 *
 * Zentrale Steuereinheit für alle administrativen Aufgaben wie Authentifizierung,
 * Dashboard-Rendering, Finanz-Exporte, Gutscheinverwaltung und Systemwartung.
 * Dient als Einstiegspunkt für den Admin-Lifecycle.
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
        private AuthService $auth,
        private BackupService $backupService,
        private ConfigInterface $config,
        private CronScheduler $cronScheduler,
        private ExportService $exportService,
        private GroupRepositoryInterface $groupRepository,
        private MailLogInterface $mailLog,
        private PermitService $permitService,
        private ReportingService $reportingService,
        private StorageBootstrapper $bootstrapper,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private VoucherRepositoryInterface $voucherRepository,
        private VoucherService $voucherService,
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

        // 2. Auth Aktionen auslesen
        $action = '';
        if (isset($post['action']) && $post['action'] === 'logout') {
            $action = 'logout';
        } elseif (isset($post['login']) || (isset($post['user'], $post['pass']))) {
            $action = 'login';
        }

        // 3. PIPELINE FÜR AUTH (Login/Logout)
        if ($action !== '') {
            $pipeline = new MiddlewarePipeline();
            $pipeline->add(new CsrfMiddleware('admin.php'));

            $pipeline->process(['post' => $post, 'get' => $get], function (array $req) use ($action): void {
                $actionHandler = $this->actionFactory->create($action);
                if ($actionHandler !== null) {
                    $actionHandler->execute($req['post']);
                }
            });

            return; // Nach Auth-Verarbeitung beenden
        }

        // 4. ZUGANGSKONTROLLE
        if (! $this->auth->isLoggedIn()) {
            $this->renderer->render('admin_login', [
                'auth'            => $this->auth,
                'groupRepository' => $this->groupRepository,
                'message'         => '',
                'userRepository'  => $this->userRepository,
            ]);

            return; // Hier ist für nicht-eingeloggte User Schluss!
        }

        // 5. PIPELINE FÜR DASHBOARD & DATEN
        $pipeline = new MiddlewarePipeline();
        $pipeline->add(new CsrfMiddleware('admin.php'));

        $pipeline->process(['post' => $post, 'get' => $get], function (array $req): void {
            $p = $req['post'];
            $g = $req['get'];

            // POST Aktionen (Gutscheine, Migrationen etc.)
            $actionKey = (string) ($p['action'] ?? '');
            if ($actionKey !== '') {
                $actionHandler = $this->actionFactory->create($actionKey);
                if ($actionHandler !== null) {
                    $message = $actionHandler->execute($p);
                    \header('Location: admin.php?msg=' . \urlencode($message));
                    exit;
                }
            }

            // Print Preview Delegation
            $getAction = (string) ($g['action'] ?? '');
            if ($getAction === 'print') {
                $printAction = $this->actionFactory->create('admin_print');
                if ($printAction !== null) {
                    $printAction->execute($g);

                    return;
                }
            }

            // Render Dashboard
            $displayMessage = (string) ($g['msg'] ?? '');
            $this->renderDashboard($g, $displayMessage);
        });
    }

    /**
     * Prüft und verarbeitet Authentifizierungs-Aktionen (Login/Logout).
     * Gibt true zurück, wenn ein Redirect erfolgt ist (Request beenden).
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     *
     * @return bool True wenn die Aktion den Request abgeschlossen hat.
     */
    private function handleAuthActions(array $get, array $post): bool
    {
        $action = (string) ($post['action'] ?? '');

        // STRIKTE HÄRTUNG: Nur definierte Auth-Actions werden hier verarbeitet
        if ($action === 'login' || $action === 'logout') {
            $actionHandler = $this->actionFactory->create($action);
            if ($actionHandler !== null) {
                $actionHandler->execute($post);

                return true; // Script wurde in der Action beendet (exit), formelles return.
            }
        }

        return false;
    }

    /**
     * Router für POST-basierte Daten-Aktionen (Migration, Gutscheine, Status-Updates).
     *
     * Nutzt 'match' für sauberes Routing. Leitet an action-Methoden weiter.
     *
     * @param array<string, mixed> $post
     *
     * @return string Ergebnisnachricht oder Fehlermeldung für die UI.
     */
    private function handleDataActions(array $post): string
    {
        // Zahlung markieren
        $action = (string) ($post['action'] ?? '');
        if ($action === '') {
            return '';
        }

        // Die Factory regelt jetzt alles!
        $actionHandler = $this->actionFactory->create($action);
        if ($actionHandler !== null) {
            return $actionHandler->execute($post);
        }

        return ''; // Unbekannte Aktionen geben einfach nichts zurück
    }

    /**
     * Rendert die Admin-Dashboard-Oberfläche mit allen Statistiken und Filterdaten (Tabellen).
     *
     * Berechnet YearlyStats, ruft calculateDetailedStats auf, verarbeitet Exporte und übergibt diverse Services.
     *
     * @param array<string, mixed> $get
     */
    private function renderDashboard(array $get, string $message): void
    {
        if (isset($get['reset_filters'])) {
            unset($_SESSION['admin_filters']);
        }
        // Holt die Filter primär aus der Session (POST), fällt zurück auf GET oder Standardwerte
        $sessionFilters = $_SESSION['admin_filters'] ?? [];

        $filterStart = (string) ($sessionFilters['start'] ?? $get['start'] ?? \date('Y-01-01'));
        $filterEnd   = (string) ($sessionFilters['end'] ?? $get['end'] ?? \date('Y-12-31'));
        $filterType  = (string) ($sessionFilters['type'] ?? $get['type'] ?? 'all');
        $searchQuery = \strtolower(\trim((string) ($sessionFilters['q'] ?? $get['q'] ?? '')));

        // Konfiguration für Paginierung auslesen
        $paginationCfg = $this->config->get('pagination', []);
        $allowedLimits = $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100];
        $defaultLimit  = (int) ($paginationCfg['default_limit'] ?? 25);

        // Prüfen, ob ein Limit übergeben wurde und ob es in der erlaubten Liste steht
        $requestedLimit = (int) ($sessionFilters['limit'] ?? $get['limit'] ?? $defaultLimit);
        $itemsPerPage   = \in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : $defaultLimit;
        $currentPage    = \max(1, (int) ($get['page'] ?? 1));

        $allPermits      = $this->storage->getAll();
        $permitTemplates = $this->config->get('permit_templates', []); // Vorlagen laden, um den Typ abzugleichen

        // 1. Filterung für den gewählten Zeitraum & Typ
        $filtered = \array_filter(
            $allPermits,
            function (Permit $permit) use ($filterStart, $filterEnd, $filterType, $permitTemplates, $searchQuery): bool {
                $date = $permit->getCreatedAt()->format('Y-m-d');

                // Check Zeitraum
                if ($date < $filterStart || $date > $filterEnd) {
                    return false;
                }

                // Check Typ (wenn nicht 'all')
                if ($filterType !== 'all') {
                    $tplType = $permitTemplates[$permit->template_key]['type'] ?? 'standard';
                    if ($tplType !== $filterType) {
                        return false;
                    }
                }

                // Volltextsuche über alle relevanten Felder
                if ($searchQuery !== '') {
                    $haystack = \strtolower(
                        $permit->code . ' ' .
                        $permit->getOwnerName() . ' ' .
                        $permit->getOwnerEmail() . ' ' .
                        $permit->getPlotNumber() . ' ' .
                        $permit->getLicensePlate() . ' ' .
                        $permit->getPurpose(),
                    );
                    if (! \str_contains($haystack, $searchQuery)) {
                        return false;
                    }
                }

                return true;
            },
        );

        // Export abfangen
        if (isset($get['export'])) {
            // Zwingende Backend-Überprüfung für Daten-Exporte!
            if (! $this->auth->hasPermission('finance.export.execute')) {
                exit('Fehler: Keine Berechtigung für Daten-Exporte.');
            }
            $this->exportService->export((string) $get['export'], $filtered, $filterStart, $filterEnd);
            exit; // Wichtig: Nach dem Download darf kein HTML mehr gesendet werden!
        }

        $vouchers          = $this->voucherRepository->loadAll();
        $voucherValidities = [];
        foreach ($vouchers as $code => $v) {
            $voucherValidities[$code] = $this->voucherService->isValid($v);
        }

        $permitGroups  = $this->reportingService->groupPermits($filtered);
        $overdueLevels = [];
        foreach ($permitGroups['unpaid'] ?? [] as $permit) {
            $overdueLevels[$permit->code] = $this->permitService->getOverdueLevel($permit);
        }

        // [x] sortiert
        // Gefilterte Daten ans Template übergeben
        // Hier wurde vorher $allPermits übergeben. Jetzt übergeben wir die $filtered Liste!
        $this->renderer->render('admin_dashboard', [
            'allowedLimits'     => $allowedLimits, // Paginierungs-Werte
            'allPermits'        => $allPermits,
            'auth'              => $this->auth,
            'backups'           => $this->backupService->listBackups(), // VORBERECHNET!
            'currentPage'       => $currentPage, // Paginierungs-Werte
            'filterEnd'         => $filterEnd,
            'filterStart'       => $filterStart,
            'filterType'        => $filterType, // An die Control-Bar übergeben
            'groupRepository'   => $this->groupRepository,
            'itemsPerPage'      => $itemsPerPage, // Paginierungs-Werte
            'mailLogs'          => $this->mailLog->loadLogs(),
            'message'           => $message,
            'overdueLevels'     => $overdueLevels, // VORBERECHNET!
            'periodStats'       => $this->reportingService->calculateDetailedStats($filtered),
            'permitGroups'      => $permitGroups,
            'structure'         => $this->config->get('structure', []),
            'userRepository'    => $this->userRepository,
            'voucherArchive'    => $this->voucherRepository->loadArchive(),
            'vouchers'          => $vouchers,
            'voucherValidities' => $voucherValidities, // VORBERECHNET!
            'yearlyStats'       => $this->reportingService->calculateYearlyStats($allPermits),
        ]);
    }
}
