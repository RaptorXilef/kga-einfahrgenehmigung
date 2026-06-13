<?php

declare(strict_types=1);

namespace App\Application;

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
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\CronScheduler;
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
        private AuthService $auth,
        private BackupService $backupService,
        private ConfigInterface $config,
        private CronScheduler $cronScheduler,
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
        private UserRepositoryInterface $userRepository,
        private VoucherRepositoryInterface $voucherRepository,
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

        // 3. ZUGANGSKONTROLLE / AUTH-GATEKEEPER
        if (! $this->auth->isLoggedIn()) {
            $this->render('admin_login', [
                'message'  => '',
                'settings' => $this->getSettingsArray(),
            ]);

            return; // Hier ist für nicht-eingeloggte User Schluss!
        }

        // 4. EIGENTLICHE DASHBOARD-LOGIK (Nur wenn eingeloggt)
        // Neu mit CSRF-Schutz
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Globale CSRF-Prüfung für alle administrativen POST-Aktionen
            if (! \hash_equals($_SESSION['csrf_token'] ?? '', $post['csrf_token'] ?? '')) {
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
            if (! \hash_equals($_SESSION['csrf_token'] ?? '', $post['csrf_token'] ?? '')) {
                $this->render('admin_login', [
                    'message'  => 'Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.',
                    'settings' => $this->getSettingsArray(),
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
                // Normaler Fehler (Passwort falsch)
                $this->render('admin_login', [
                    'message'  => 'Benutzername oder Passwort ist falsch.',
                    'settings' => $this->getSettingsArray(),
                ]);
                exit;
            } catch (\RuntimeException $e) {
                // Rate Limit Fehler abfangen
                $this->render('admin_login', [
                    'message'  => $e->getMessage(),
                    'settings' => $this->getSettingsArray(),
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

        // Aufteilung in Unter-Methoden zur Senkung der Komplexität
        // [x] Sortiert
        return match ($action) {
            'activate_voucher'   => $this->actionToggleVoucher($post),
            'anonymize_archive'  => $this->actionAnonymizeArchive($post),
            'clear_cache'        => $this->actionClearCache($post),
            'create_manual'      => $this->actionCreateManual($post),
            'create_voucher'     => $this->actionCreateVoucher($post),
            'deactivate_voucher' => $this->actionToggleVoucher($post),
            'delete_voucher'     => $this->actionDeleteVoucher($post),
            'filter_dashboard'   => $this->actionFilterDashboard($post),
            'mark_as_paid'       => $this->actionMarkAsPaid($post),
            'migrate_data'       => $this->actionMigrateData($post),
            'resend_mail'        => $this->actionResendMail($post),
            'restore_data'       => $this->actionRestoreData($post),
            'suspend_permit'     => $this->actionToggleSuspension($post),
            'truncate_target'    => $this->actionTruncateTarget($post),
            'unsuspend_permit'   => $this->actionToggleSuspension($post),
            default              => '',
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

    // 2. Genehmigungs-Management

    /**
     * Erstellt eine Genehmigung ohne vorangegangenen automatisierten Bezahlprozess.
     *
     * Erzwingt 'status' = 'bezahlt' und nutzt PermitService::createPermit().
     *
     * @param array<string, mixed> $post
     *
     * @return string Bestätigung mit dem generierten Genehmigungscode.
     */
    private function actionCreateManual(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.direct_issue.execute')) {
            return 'Fehler: Sie haben keine Berechtigung für manuelle Ausstellungen.';
        }

        $tplKey = (string) ($post['template_key'] ?? 'std.7');

        // --- BACKEND SECURITY CHECK ---
        if (! $this->auth->hasPermission("template.$tplKey")) {
            return "Fehler: Sie haben keine Berechtigung, den Typ '$tplKey' manuell auszustellen.";
        }

        // Manuelle Buchung (Kostenlos/Bar)
        try {
            $post['status'] = 'bezahlt';
            if (isset($post['reason'])) {
                $post['interner_kommentar'] = $post['reason'];
            }

            $permit = $this->permitService->createPermit($post, true);

            return "Manuelle Genehmigung erstellt: <strong>{$permit->code}</strong>";
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }

    /**
     * Markiert eine Genehmigung manuell als bezahlt im Storage.
     *
     * Nutzt PermitService::manualActivate().
     *
     * @param array<string, mixed> $post
     *
     * @return string Erfolgsmeldung oder leerer String bei Fehler.
     */
    private function actionMarkAsPaid(array $post): string
    {
        // Zwingende Backend-Rechteprüfung ergänzt!
        if (! $this->auth->hasPermission('dashboard.finance.mark_paid')) {
            return 'Fehler: Keine Berechtigung für diese Aktion.';
        }

        $code = (string) ($post['code'] ?? '');

        return $this->permitService->manualActivate($code) ? "Zahlung für $code bestätigt." : '';
    }

    /**
     * Setzt den Sperrstatus (Suspension) einer Genehmigung.
     * Kontext: Interaktion mit PermitService::toggleSuspension().
     */
    private function actionToggleSuspension(array $post): string
    {
        $code   = (string) ($post['code'] ?? '');
        $permit = $this->permitService->getStorage()->findByHash($code);

        if (! $permit instanceof Permit) {
            return 'Fehler: Genehmigung nicht gefunden.';
        }

        $isUnpaid = \strtolower(\trim($permit->status->current)) !== 'bezahlt';

        // Kontext-sensitive Sperr-Prüfung (State-Aware Access Control)
        $hasRight = false;
        if ($isUnpaid && $this->auth->hasPermission('dashboard.finance.suspend')) {
            $hasRight = true; // Darf unbezahlte sperren
        } elseif (! $isUnpaid && $this->auth->hasPermission('dashboard.active.suspend')) {
            $hasRight = true; // Darf bezahlte/aktive sperren
        }

        if (! $hasRight) {
            return 'Fehler: Keine Berechtigung, diesen spezifischen Status zu sperren/entsperren.';
        }

        $suspended = ($post['action'] ?? '') === 'suspend_permit';
        $this->permitService->toggleSuspension($code, $suspended, (string) ($post['reason'] ?? ''));

        return 'Genehmigung wurde ' . ($suspended ? 'gesperrt.' : 'freigegeben.');
    }

    // 3. Gutschein-Management

    /**
     * Erstellt einen neuen Gutschein mit spezifischen Konditionen über VoucherService.
     *
     * Kontext: Beinhaltet Sicherheitsprüfung (hasPermission). Übergibt diverse Gutschein-Parameter.
     *
     * @param array<string, mixed> $post
     *
     * @return string Bestätigung mit dem generierten Gutscheincode.
     */
    private function actionCreateVoucher(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.voucher_gen.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, Gutscheine zu erstellen.';
        }

        $tplKey = (string) ($post['template_key'] ?? 'std.7');

        // --- BACKEND SECURITY CHECK ---
        if (! $this->auth->hasPermission("template.$tplKey")) {
            return "Fehler: Sie haben keine Berechtigung, den Typ '$tplKey' zu verwenden.";
        }

        try {
            // Gutschein erstellen und Erweiterte Gutschein-Parameter
            $reason     = (string) ($post['reason'] ?? 'Gutschein');
            $type       = (string) ($post['voucher_discount_type'] ?? 'free');
            $val        = (float) ($post['voucher_discount_value'] ?? 0.0);
            $multi      = isset($post['voucher_multi_use']);
            $max_uses   = $multi ? (int) ($post['voucher_max_uses'] ?? 1) : 1;
            $custom     = (string) ($post['voucher_custom_code'] ?? '');
            $expires_at = (string) ($post['voucher_expires_at'] ?? '');
            $date_mode  = (string) ($post['voucher_date_mode'] ?? 'fixed');

            $preData = [
                'name'        => \trim(\strip_tags((string) ($post['name'] ?? ''))),
                'parzelle'    => \trim(\strip_tags((string) ($post['parzelle'] ?? ''))),
                'kennzeichen' => \trim(\strip_tags((string) ($post['kennzeichen'] ?? ''))),
                'typ'         => (string) ($post['typ'] ?? ''),
                'firma'       => \trim(\strip_tags((string) ($post['firma'] ?? ''))),
                'zweck'       => \strip_tags((string) ($post['zweck'] ?? '')),
                'datum_von'   => $date_mode === 'fixed' ? (string) ($post['datum_von'] ?? '') : '',
                'datum_bis'   => $date_mode === 'fixed' ? (string) ($post['datum_bis'] ?? '') : '',
            ];

            // Service-Aufruf mit neuen Parametern
            $code = $this->permitService->getVoucherService()->createVoucher(
                $reason,
                (string) ($_SESSION['user_id'] ?? 'sys_admin'), // Geändert von 'Admin' auf 'sys_admin'
                $tplKey,
                $preData,
                $type,
                $val,
                $multi,
                $max_uses,
                $custom, // Weitergabe an Service
                $expires_at ?: null, // In null wandeln wenn leer
                $date_mode,
            );

            return "Gutschein erstellt: <strong>$code</strong>";
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }

    /**
     * Setzt den Sperrstatus einer bestehenden Genehmigung.
     *
     * @param array<string, mixed> $post
     *
     * @return string Statusänderungs-Meldung.
     */
    private function actionToggleVoucher(array $post): string
    {
        // Zwingende Backend-Rechteprüfung!
        if (! $this->auth->hasPermission('dashboard.vouchers.suspend')) {
            return 'Fehler: Keine Berechtigung für diese Aktion.';
        }

        // Gutschein sperren / aktivieren
        $status = $post['action'] === 'activate_voucher' ? 'aktiv' : 'deaktiviert';
        $this->permitService->getVoucherService()->toggleStatus((string) $post['code'], $status);

        return 'Gutschein wurde ' . ($status === 'aktiv' ? 'reaktiviert.' : 'gesperrt.');
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

    /**
     * TODO DOCBLOCK
     * Löscht einen Gutschein unwiderruflich.
     */
    private function actionDeleteVoucher(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.vouchers.remove')) {
            return 'Fehler: Keine Berechtigung zum Löschen von Gutscheinen.';
        }

        $code = (string) ($post['code'] ?? '');

        return $this->permitService->getVoucherService()->deleteVoucher($code)
            ? "Gutschein '$code' wurde unwiderruflich gelöscht."
            : 'Fehler: Gutschein nicht gefunden.';
    }

    /**
     * Leert den Anwendungs-Cache und löscht temporäre System-Dateien.
     *
     * @param array<string, mixed> $post Formulardaten inklusive CSRF-Token.
     *
     * @return string Statusmeldung über die Ausführung.
     */
    private function actionClearCache(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.migration.delete-cache.execute')) {
            return 'Fehler: Sie haben keine Berechtigung für diese Aktion.';
        }

        return $this->migrationService->clearCache();
    }

    // --- ENDE Aktions-Methoden (die von handleDataActions aufgerufen werden) ---

    /**
     * Rendert ein Template-File mit den übergebenen Daten.
     *
     * Nutzt 'extract' um Array-Keys zu lokalen Variablen zu machen.
     *
     * @param string               $templatePath Pfad zum .phtml Template.
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data = []): void
    {
        $config = $this->config;
        // Sicherstellen, dass appRoot für das Template immer auf einem Slash endet:
        $appRoot = \rtrim((string) $config->get('root_path'), '/\\');

        // Wir fügen auth global hinzu, falls es mal vergessen wird
        if (! isset($data['auth'])) {
            $data['auth'] = $this->auth;
        }
        $data['userRepository']  = $this->userRepository;
        $data['groupRepository'] = $this->groupRepository;

        // Macht aus ['stats' => $stats] echte Variablen im lokalen Scope
        // Zwingender Sicherheits-Fix gegen Variable Overwrite / LFI
        \extract($data, \EXTR_SKIP);
        // IMPORTANT: Hier muss ein / zwischen $appRoot und templates
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
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
            function (Permit $p) use ($filterStart, $filterEnd, $filterType, $permitTemplates, $searchQuery): bool {
                $date = $p->erstellt->format('Y-m-d');

                // Check Zeitraum
                if ($date < $filterStart || $date > $filterEnd) {
                    return false;
                }

                // Check Typ (wenn nicht 'all')
                if ($filterType !== 'all') {
                    $tplType = $permitTemplates[$p->template_key]['type'] ?? 'standard';
                    if ($tplType !== $filterType) {
                        return false;
                    }
                }

                // Volltextsuche über alle relevanten Felder
                if ($searchQuery !== '') {
                    $haystack = \strtolower(
                        $p->code . ' ' .
                            $p->owner->name . ' ' .
                            $p->owner->email . ' ' .
                            $p->owner->parzelle . ' ' .
                            $p->vehicle->kennzeichen . ' ' .
                            $p->validity->zweck,
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
            // FIX: Zwingende Backend-Überprüfung für Daten-Exporte!
            if (! $this->auth->hasPermission('finance.export.execute')) {
                exit('Fehler: Keine Berechtigung für Daten-Exporte.');
            }
            $this->handleExport((string) $get['export'], $filtered, $filterStart, $filterEnd);
            exit; // Wichtig: Nach dem Download darf kein HTML mehr gesendet werden!
        }

        // 2. Jährliche Gruppierung
        $yearlyStats = [];
        $vConfig     = $this->config->get('vehicle_types', []);

        // Gefilterte Daten ans Template übergeben
        // Hier wurde vorher $allPermits übergeben. Jetzt übergeben wir die $filtered Liste!
        $this->render('admin_dashboard', [
            'allowedLimits'    => $allowedLimits, // Paginierungs-Werte
            'appRoot'          => $this->config->get('root_path'),
            'auth'             => $this->auth,
            'backupService'    => $this->backupService,
            'config'           => $this->config,
            'currentPage'      => $currentPage, // Paginierungs-Werte
            'filterEnd'        => $filterEnd,
            'filterStart'      => $filterStart,
            'filterType'       => $filterType, // An die Control-Bar übergeben
            'itemsPerPage'     => $itemsPerPage, // Paginierungs-Werte
            'mailLogs'         => $this->mailLog->loadLogs(),
            'message'          => $message,
            'migrationService' => $this->migrationService,
            'periodStats'      => $this->reportingService->calculateDetailedStats($filtered),
            'permitGroups'     => $this->reportingService->groupPermits($filtered),
            'permitService'    => $this->permitService,
            'settings'         => $this->getSettingsArray(),
            'structure'        => $this->config->get('structure', []),
            'vouchers'         => $this->voucherRepository->loadAll(),
            'voucherArchive'   => $this->voucherRepository->loadArchive(),
            'voucherService'   => $this->permitService->getVoucherService(),
            'yearlyStats'      => $this->reportingService->calculateYearlyStats($allPermits),
        ]);
    }

    /**
     * Exportiert gefilterte Genehmigungsdaten als CSV oder JSON.
     *
     * Setzt Header und UTF-8-BOM für Excel-Kompatibilität.
     *
     * @param string             $format   'csv' oder 'json'.
     * @param array<int, Permit> $filtered
     * @param string             $start    Startdatum (Y-m-d).
     * @param string             $end      Enddatum (Y-m-d).
     */
    private function handleExport(string $format, array $filtered, string $start, string $end): void
    {
        // Wir nutzen den Vereinsnamen aus der Config für den Dateinamen (statt hartkodiert "kga")
        $slug = \strtolower(
            (string) \preg_replace(
                '/[^A-Za-z0-9]/',
                '_',
                (string) $this->config->get('vereins_name', 'export'),
            ),
        );

        // Die Endung wird jetzt dynamisch über $format angehängt
        $filename = "export_{$slug}_{$start}_bis_{$end}.{$format}";

        $settings = $this->getSettingsArray();

        if ($format === 'csv') {
            \header('Content-Type: text/csv; charset=utf-8');
            \header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = \fopen('php://output', 'w');
            if ($output) {
                // 1. FEINHEIT: UTF-8 BOM für Excel (zwingend nötig für Umlaute)
                \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF)); // UTF-8 BOM

                // Hinzufügen der Parameter für Umschließung und Escape (verhindert Warning)
                \fputcsv($output, [
                    'Kennung',
                    'Name',
                    'E-Mail',
                    'Parzelle',
                    'Typ',
                    'Kennzeichen',
                    'Firma',
                    'Zweck',
                    'Einnahme (€)',
                    'Status',
                    'Erstellt am',
                ], ';', '"', '\\');

                foreach ($filtered as $permit) {
                    $row = [
                        $permit->code,
                        $permit->owner->name,
                        $permit->owner->email,
                        $permit->owner->parzelle,
                        $settings['vehicle_types'][$permit->vehicle->typ] ?? $permit->vehicle->typ,
                        $permit->vehicle->kennzeichen,
                        $permit->vehicle->firma ?? '',
                        $permit->validity->zweck,
                        \number_format($permit->validity->preis, 2, ',', ''),
                        \strtoupper($permit->status->current),
                        $permit->erstellt->format('d.m.Y H:i'),
                    ];

                    // CSV-Injection-Schutz (Formel-Neutralisierung)
                    // Wir iterieren per Referenz, um den Wert direkt im Array zu maskieren
                    foreach ($row as &$cell) {
                        $firstChar = \substr((string) $cell, 0, 1);
                        if ($cell !== '' && \in_array($firstChar, ['=', '+', '-', '@', '\t', '\r'], true)) {
                            $cell = "'" . $cell;
                        }
                    }
                    unset($cell); // Referenz sauber trennen

                    \fputcsv($output, $row, ';', '"', '\\');
                }
                \fclose($output);
            }

            return;
        }

        if ($format !== 'json') {
            return;
        }

        \header('Content-Type: application/json');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo \json_encode(\array_values($filtered), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
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
        $isExpired = $permit->validity->bis < $now;
        $isFuture  = $permit->validity->von > $now;

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
        $this->render('admin_print_view', [
            'permit'       => $permit,
            'settings'     => $this->getSettingsArray(),
            'config'       => $config,
            'appRoot'      => $config->get('root_path'),
            'opening_html' => $this->holidayService->getOpeningHoursTextForDateRange(
                $permit->validity->von,
                $permit->validity->bis,
            ),
            'holidayNotice' => $this->holidayService->getHolidaysInRangeText(
                $permit->validity->von,
                $permit->validity->bis,
            ),
        ]);

        return true;
    }

    /**
     * Liefert Konfigurations-Settings für das Frontend-Mapping/Templates.
     *
     * Schnittstelle zwischen Config-Objekt und UI.
     *
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'       => $this->config->get('vereins_name'),
            'vehicle_types'      => $this->config->get('vehicle_types'),
            'purposes'           => $this->config->get('purposes'),
            'opening_hours'      => $this->config->get('default_opening_hours'),
            'jahresFarbe'        => $this->config->get('jahresFarbe'),
            'base_url'           => $this->config->getBaseUrl(),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
        ];
    }
}
