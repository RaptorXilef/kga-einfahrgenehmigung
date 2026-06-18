<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\ExportService;
use App\Core\Service\HolidayService;
use App\Core\Service\Maintenance\CronScheduler;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\MigrationService;
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
        private HolidayService $holidayService,
        private MailLogInterface $mailLog,
        private MigrationService $migrationService,
        private PermitArchiveRepositoryInterface $archiveRepository,
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

            // Print Preview
            if ($this->shouldStopRequest($g)) {
                return;
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
        $filterType  = (string) ($sessionFilters['type'] ?? $get['type'] ?? 'all'); // Den Typ-Filter aus der URL auslesen
        $searchQuery = \strtolower(\trim((string) ($sessionFilters['q'] ?? $get['q'] ?? ''))); // Die Suche

        // Konfiguration für Paginierung auslesen
        $paginationCfg = $this->config->get('pagination', []);
        $allowedLimits = $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100];
        $defaultLimit  = (int) ($paginationCfg['default_limit'] ?? 25);

        // Prüfen, ob ein Limit übergeben wurde und ob es in der erlaubten Liste steht
        $requestedLimit = (int) ($sessionFilters['limit'] ?? $get['limit'] ?? $defaultLimit);
        $itemsPerPage   = \in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : $defaultLimit;

        $currentPage = \max(1, (int) ($get['page'] ?? 1));

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

        // 2. Jährliche Gruppierung
        $yearlyStats = [];
        $vConfig     = $this->config->get('vehicle_types', []);

        // [x] sortiert
        // Gefilterte Daten ans Template übergeben
        // Hier wurde vorher $allPermits übergeben. Jetzt übergeben wir die $filtered Liste!
        $this->renderer->render('admin_dashboard', [
            'allowedLimits'    => $allowedLimits, // Paginierungs-Werte
            'allPermits'       => $allPermits,
            'auth'             => $this->auth,
            'backupService'    => $this->backupService,
            'currentPage'      => $currentPage, // Paginierungs-Werte
            'filterEnd'        => $filterEnd,
            'filterStart'      => $filterStart,
            'filterType'       => $filterType, // An die Control-Bar übergeben
            'groupRepository'  => $this->groupRepository,
            'itemsPerPage'     => $itemsPerPage, // Paginierungs-Werte
            'mailLogs'         => $this->mailLog->loadLogs(),
            'message'          => $message,
            'migrationService' => $this->migrationService,
            'periodStats'      => $this->reportingService->calculateDetailedStats($filtered),
            'permitGroups'     => $this->reportingService->groupPermits($filtered),
            'permitService'    => $this->permitService,
            'structure'        => $this->config->get('structure', []),
            'userRepository'   => $this->userRepository,
            'voucherArchive'   => $this->voucherRepository->loadArchive(),
            'vouchers'         => $this->voucherRepository->loadAll(),
            'voucherService'   => $this->voucherService,
            'yearlyStats'      => $this->reportingService->calculateYearlyStats($allPermits),
        ]);
    }

    /**
     * Prüft, ob ein spezieller Request (z.B. Druckansicht) abgebrochen werden muss.
     *
     * Überprüft Zugriff und rendert 'admin_print_view'.
     *
     * @param array<string, mixed> $get
     *
     * @return bool True wenn Print-View gerendert wurde.
     */
    private function shouldStopRequest(array $get): bool
    {
        // Wenn die Bedingung nicht zutrifft, muss hier ein 'false' zurückgegeben werden
        if (! isset($get['action']) || $get['action'] !== 'print' || ! isset($get['code'])) {
            return false;
        }

        // Zuerst das Objekt laden, um den Zustand zu prüfen!
        $permit = $this->storage->findByHash((string) $get['code']);
        if (! $permit instanceof Permit) {
            return false;
        }

        $now       = new \DateTimeImmutable('today');
        $isExpired = $permit->getValidUntil() < $now;
        $isFuture  = $permit->getValidFrom() > $now;

        // Kontext-sensitive Rechteprüfung (State-Aware Access Control)
        $hasRight = false;
        if ($this->auth->hasPermission('check.admin.print')) {
            $hasRight = true;
        } elseif ($isExpired && $this->auth->hasPermission('dashboard.expired.print')) {
            $hasRight = true;
        } elseif ($isFuture && $this->auth->hasPermission('dashboard.future.print')) {
            $hasRight = true;
        } elseif (! $isExpired && ! $isFuture && $this->auth->hasPermission('dashboard.active.print')) {
            $hasRight = true;
        }

        if (! $hasRight) {
            exit('Fehler: Sie haben keine Berechtigung, Genehmigungen in diesem spezifischen Status zu drucken.');
        }

        $config = $this->config;
        // [x] sortiert
        $this->renderer->render('admin_print_view', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'holidayNotice'   => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange(
                    $permit->getValidFrom(),
                    $permit->getValidUntil(),
                ),
            ),
            'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange(
                    $permit->getValidFrom(),
                    $permit->getValidUntil(),
                ),
            ),
            'permit'         => $permit,
            'userRepository' => $this->userRepository,
        ]);

        return true;
    }
}
