<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminActionFactory;
use App\Application\Security\CsrfHelper;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
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
use App\Infrastructure\Storage\JsonHelper;

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
        private MailServiceInterface $mailService,
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
        // 1. SYSTEM-INITIALISIERUNG & SEEDING (Muss VOR dem Login passieren!)
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

        // ---------------------------------------------------------------

        // 2. AUTH-AKTIONEN VERARBEITEN (z.B. Login-Formular absenden)
        if ($this->handleAuthActions($get, $post)) {
            return;
        }

        // [x] sortiert
        // 3. ZUGANGSKONTROLLE / AUTH-GATEKEEPER
        if (! $this->auth->isLoggedIn()) {
            $this->renderer->render('admin_login', [
                'auth'            => $this->auth,
                'groupRepository' => $this->groupRepository,
                'message'         => '',
                'userRepository'  => $this->userRepository,
            ]);

            return; // Hier ist für nicht-eingeloggte User Schluss!
        }

        // 4. EIGENTLICHE DASHBOARD-LOGIK (Nur wenn eingeloggt)
        // Neu mit CSRF-Schutz
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Globale CSRF-Prüfung für alle administrativen POST-Aktionen
            if (! CsrfHelper::verify($post)) {
                $message = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
            } else {
                $message = $this->handleDataActions($post);
            }

            // WICHTIG: Wenn eine Aktion verarbeitet wurde -> Redirect (PRG Pattern)
            if ($message !== '') {
                \header('Location: admin.php?msg=' . \urlencode($message));
                exit;
            }
        }

        // Nachricht aus der URL holen (falls wir gerade umgeleitet wurden)
        $displayMessage = (string) ($get['msg'] ?? '');

        // 3. Print Preview abfangen
        if ($this->shouldStopRequest($get)) {
            return;
        }

        // 4. View-Logik (Dashboard & Export)
        // Wir übergeben $displayMessage statt der flüchtigen POST-Nachricht
        $this->renderDashboard($get, $displayMessage);
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
        // STRIKTE HÄRTUNG: Logout reagiert aus Sicherheitsgründen AUSSCHLIESSLICH auf POST
        if (isset($post['action']) && $post['action'] === 'logout') {
            $this->auth->logout();
            \header('Location: admin.php');

            return true;
        }

        // Neu mit CSRF-Schutz
        if (isset($post['login'])) {
            // CSRF-Schutz auch für das Login-Formular
            if (! CsrfHelper::verify($post)) {
                $this->renderer->render('admin_login', [
                    'auth'            => $this->auth,
                    'groupRepository' => $this->groupRepository,
                    'message'         => 'Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.',
                    'userRepository'  => $this->userRepository,
                ]);
                exit;
            }

            $user = (string) ($post['user'] ?? '');
            $pass = (string) ($post['pass'] ?? '');

            try {
                if ($this->auth->login($user, $pass)) {
                    // Login-Redirects behalten POST/REQUEST-Fokus bei, leite direkt zur Prüfung weiter
                    $code = (string) ($_REQUEST['code'] ?? '');
                    if ($code !== '') {
                        \header('Location: check.php?code=' . \urlencode($code));
                        exit;
                    }

                    \header('Location: admin.php');

                    return true;
                }
                // [x] sortiert
                // Normaler Fehler (Passwort falsch)
                $this->renderer->render('admin_login', [
                    'auth'            => $this->auth,
                    'groupRepository' => $this->groupRepository,
                    'message'         => 'Benutzername oder Passwort ist falsch.',
                    'userRepository'  => $this->userRepository,
                ]);
                exit;
            } catch (\RuntimeException $e) {
                // Rate Limit Fehler abfangen
                $this->renderer->render('admin_login', [
                    'auth'            => $this->auth,
                    'groupRepository' => $this->groupRepository,
                    'message'         => $e->getMessage(),
                    'userRepository'  => $this->userRepository,
                ]);
                exit;
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

        // 1. Prüfen, ob wir die Aktion schon als saubere Action-Klasse haben
        $actionHandler = $this->actionFactory->create($action);
        if ($actionHandler !== null) {
            return $actionHandler->execute($post);
        }

        // Aufteilung in Unter-Methoden zur Senkung der Komplexität
        // TODO 2. Fallback: Alte Methoden (werden im Laufe des Refactorings immer weniger)
        // [x] Sortiert
        return match ($action) {
            'anonymize_archive' => $this->actionAnonymizeArchive($post),
            'filter_dashboard'  => $this->actionFilterDashboard($post),
            'migrate_data'      => $this->actionMigrateData($post),
            'resend_mail'       => $this->actionResendMail($post),
            'restore_data'      => $this->actionRestoreData($post),
            'truncate_target'   => $this->actionTruncateTarget($post),
            default             => '',
        };
    }

    // --- START Aktions-Methoden (die von handleDataActions aufgerufen werden) ---
    // 1. Dashboard UI-Steuerung

    /**
     * Hilfsmethode zum Speichern der Dashboard-Filter in der aktuellen Session.
     *
     * @param array<string, mixed> $post Das POST-Array mit den Filterdaten.
     *
     * @return string Statusmeldung über den Erfolg der Anwendung.
     */
    private function actionFilterDashboard(array $post): string
    {
        $_SESSION['admin_filters'] = [
            'end'   => (string) ($post['end'] ?? ''),
            'limit' => (int) ($post['limit'] ?? 25),
            'q'     => (string) ($post['q'] ?? ''),
            'start' => (string) ($post['start'] ?? ''),
            'type'  => (string) ($post['type'] ?? 'all'),
        ];

        return 'Filter angewendet.';
    }

    // 4. E-Mail-Verwaltung

    /**
     * Trigger für den Neuversand von E-Mails basierend auf den System-Logs.
     *
     * @param array<string, mixed> $post
     *
     * @return string Statusmeldung über den Erfolg des Neuversands.
     */
    private function actionResendMail(array $post): string
    {
        // Reines 'view' Recht reicht nicht aus, um System-Mails abzufeuern!
        // Wir fordern zusätzlich das Recht zur aktiven Dokumentenausstellung.
        if (! $this->auth->hasPermission('dashboard.logs.view') || ! $this->auth->hasPermission('dashboard.generator-tools.direct_issue.execute')) {
            return 'Fehler: Keine Berechtigung zum aktiven Neuversand von E-Mails.';
        }

        $timestamp = (string) ($post['timestamp'] ?? '');
        $logs      = $this->mailLog->loadLogs();

        foreach ($logs as $log) {
            if (! (($log['timestamp'] ?? '') === $timestamp)) {
                continue;
            }

            $payload = $log['data'] ?? [];

            // Wenn aus MySQL geladen, ist data ein JSON-String und muss decodiert werden
            if (\is_string($payload)) {
                $payload = JsonHelper::decode($payload);
            }

            if (empty($payload)) {
                return 'Fehler: Alter Log-Eintrag (Keine Rohdaten für Neuversand vorhanden).';
            }

            // Erneuter Versand über das Template-System
            $this->mailService->sendTemplate(
                $log['recipient'],
                $log['subject'],
                $log['template'],
                $payload,
            );

            return "E-Mail an {$log['recipient']} wurde erfolgreich erneut versendet.";
        }

        return 'Fehler: Log-Eintrag nicht gefunden.';
    }

    // 5. System-Wartung & Migration

    /**
     * Führt Daten-Migrationen (Sync/Backup) durch (Sync SQL/JSON).
     *
     * @param array<string, mixed> $post
     *
     * @return string Ergebnis der Migration.
     */
    private function actionMigrateData(array $post): string
    {
        $direction = (string) ($post['direction'] ?? 'sync');
        $target    = (string) ($post['target'] ?? '');

        // Dynamische Sicherheitsprüfung basierend auf der Baumstruktur!
        // Prüft exakt das Recht, z.B. 'dashboard.migration.users.json_to_mysql'
        if (! $this->auth->hasPermission("dashboard.migration.{$target}.{$direction}")) {
            return 'Fehler: Sie haben keine Berechtigung für diese Migrations-Aktion.';
        }

        return $this->migrationService->execute($target, $direction);
    }

    /**
     * Führt eine System-Wiederherstellung (Restore) aus einem Backup durch.
     * Stellt Daten für das angegebene Ziel aus dem gewählten Zeitstempel wieder her.
     *
     * @param array<string, mixed> $post Formulardaten mit Ziel (target), Zeitstempel (timestamp) und Engine.
     *
     * @return string Statusmeldung über den Erfolg oder Misserfolg des Restores.
     */
    private function actionRestoreData(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.restore.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, eine System-Wiederherstellung durchzuführen.';
        }

        $target    = (string) ($post['target'] ?? '');
        $timestamp = (string) ($post['timestamp'] ?? '');
        $engine    = (string) ($post['engine'] ?? 'all');

        if ($target === '' || $timestamp === '') {
            return 'Fehler: Unvollständige Angaben für Restore.';
        }

        return $this->migrationService->restore($timestamp, $target, $engine);
    }

    /**
     * Löscht alle Daten eines bestimmten Speicher-Ziels rigoros (Truncate).
     * Wird für administrative System-Resets oder vor großen Migrationen verwendet.
     *
     * @param array<string, mixed> $post Formulardaten mit Zielbereich (target) und Speicher-Engine (engine).
     *
     * @return string Statusmeldung über die Löschung.
     */
    private function actionTruncateTarget(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.delete-data.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, Datenbestände zu löschen.';
        }

        $target = (string) ($post['target'] ?? '');
        $engine = (string) ($post['engine'] ?? 'all');

        if ($target === '') {
            return 'Fehler: Kein Zielbereich ausgewählt.';
        }

        return $this->migrationService->truncateTarget($target, $engine);
    }

    /**
     * Führt die DSGVO-konforme Anonymisierung von alten Archiv-Einträgen durch.
     *
     * @param array<string, mixed> $post Das POST-Array der Anfrage.
     *
     * @return string Status- oder Erfolgsmeldung über die Anzahl anonymisierter Einträge.
     */
    private function actionAnonymizeArchive(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.anonymize.execute')) {
            return 'Fehler: Sie haben keine Berechtigung für die DSGVO-Anonymisierung.';
        }

        try {
            // 10 Jahre gesetzliche Aufbewahrungsfrist
            $count = $this->archiveRepository->anonymizeOldRecords(10);

            if ($count === 0) {
                return 'Hinweis: Es wurden keine Archiv-Einträge gefunden, die älter als 10 Jahre sind.';
            }

            return "Erfolg: Es wurden $count alte Archiv-Einträge DSGVO-konform anonymisiert.";
        } catch (\Exception $e) {
            return 'Fehler bei der Anonymisierung: ' . $e->getMessage();
        }
    }

    // --- ENDE Aktions-Methoden (die von handleDataActions aufgerufen werden) ---

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
