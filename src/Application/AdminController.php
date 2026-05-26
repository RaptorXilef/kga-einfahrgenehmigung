<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\HolidayService;
use App\Core\Service\MigrationService;
use App\Core\Service\PermitService;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;

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
        private ConfigInterface $config,
        private AuthService $auth,
        private StorageInterface $storage,
        private PermitService $permitService,
        private MigrationService $migrationService,
        private MailServiceInterface $mailService,
        private HolidayService $holidayService,
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
        // 1. Authentifizierung & Globale Aktionen (Logout/Login-Versuch)
        if ($this->handleAuthActions($get, $post)) {
            return;
        }

        // --- AUTH-GATEKEEPER ---
        if (! $this->auth->isLoggedIn()) {
            $this->render('admin_login', [
                'message'  => '',
                'settings' => $this->getSettingsArray(),
            ]);

            return; // Hier ist für nicht-eingeloggte User Schluss!
        }

        // --- FIX ANFRAGE 1: SETUP & WARTUNG HIERHER VERSCHOBEN ---
        // Wartungsarbeiten werden nur ausgeführt, wenn der Admin EINGELOGGT ist.
        try {
            // --- SETUP & WARTUNG IMMER ZUERST (Vor dem Login-Check!) ---
            // So werden fehlende JSON-Dateien oder SQL-Tabellen angelegt,
            // noch bevor die Login-Maske erscheint.

            // Tabellen sicherstellen
            $this->migrationService->ensureTablesExist();

            // Daten impfen (wenn leer)
            $this->migrationService->seedInitialData();

            // Auto-Backup prüfen
            $this->migrationService->checkAutoBackup();
        } catch (\Throwable $e) {
            // Fängt Fehler ab, damit das Dashboard nicht abstürzt
            \error_log('MigrationService Warning: ' . $e->getMessage());
        }
        // ---------------------------------------------------------------

        // 2. Daten-Aktionen verarbeiten (nur wenn eingeloggt)
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = $this->handleDataActions($post);

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
        if (isset($get['action']) && $get['action'] === 'logout') {
            $this->auth->logout();
            \header('Location: admin.php');

            return true;
        }

        if (isset($post['login'])) {
            $user = (string) ($post['user'] ?? '');
            $pass = (string) ($post['pass'] ?? '');

            if ($this->auth->login($user, $pass)) {
                // Wenn ein Code per GET oder POST übergeben wurde, leite direkt zur Prüfung weiter
                $code = (string) ($_REQUEST['code'] ?? '');
                if ($code !== '') {
                    \header('Location: check.php?code=' . \urlencode($code));
                    exit;
                }

                \header('Location: admin.php');

                return true;
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
        // Bestehende Logik: Zahlung markieren
        $action = (string) ($post['action'] ?? '');
        if ($action === '') {
            return '';
        }

        // Aufteilung in Unter-Methoden zur Senkung der Komplexität
        return match ($action) {
            'migrate_data'   => $this->actionMigrateData($post),
            'restore_data'   => $this->actionRestoreData($post),
            'resend_mail'    => $this->actionResendMail($post),
            'mark_as_paid'   => $this->actionMarkAsPaid($post),
            'create_voucher' => $this->actionCreateVoucher($post),
            'create_manual'  => $this->actionCreateManual($post),
            'activate_voucher',
            'deactivate_voucher' => $this->actionToggleVoucher($post),
            'unsuspend_permit',
            'suspend_permit' => $this->actionToggleSuspension($post),
            default          => '',
        };
    }

    /**
     * Trigger für den Neuversand von E-Mails basierend auf den System-Logs.
     *
     * @param array<string, mixed> $post
     *
     * @return string Statusmeldung über den Erfolg des Neuversands.
     */
    private function actionResendMail(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.logs.view')) {
            return 'Fehler: Keine Berechtigung.';
        }

        $timestamp = (string) ($post['timestamp'] ?? '');
        $logs      = $this->mailService->loadLogs();

        foreach ($logs as $log) {
            if (! (($log['timestamp'] ?? '') === $timestamp)) {
                continue;
            }

            $payload = $log['data'] ?? [];

            // Wenn aus MySQL geladen, ist data ein JSON-String und muss decodiert werden
            if (\is_string($payload)) {
                $payload = \json_decode($payload, true) ?? [];
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
        $code = (string) ($post['code'] ?? '');

        return $this->permitService->manualActivate($code) ? "Zahlung für $code bestätigt." : '';
    }

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
        $tplKey = (string) ($post['template_key'] ?? 'std.7');

        // --- BACKEND SECURITY CHECK ---
        if (! $this->auth->hasPermission("template.$tplKey")) {
            return "Fehler: Sie haben keine Berechtigung, den Typ '$tplKey' zu verwenden.";
        }

        try {
            // Gutschein erstellen
            $reason = (string) ($post['reason'] ?? 'Gutschein');
            $tplKey = (string) ($post['template_key'] ?? 'std_7');
            // Erweiterte Gutschein-Parameter
            $type      = (string) ($post['voucher_discount_type'] ?? 'free');
            $val       = (float) ($post['voucher_discount_value'] ?? 0.0);
            $multi     = isset($post['voucher_multi_use']);
            $maxUses   = $multi ? (int) ($post['voucher_max_uses'] ?? 1) : 1;
            $custom    = (string) ($post['voucher_custom_code'] ?? '');
            $expiresAt = (string) ($post['voucher_expires_at'] ?? '');
            $dateMode  = (string) ($post['voucher_date_mode'] ?? 'fixed');

            $preData = [
                'name'        => \trim((string) ($post['name'] ?? '')),
                'parzelle'    => \trim((string) ($post['parzelle'] ?? '')),
                'kennzeichen' => \trim((string) ($post['kennzeichen'] ?? '')),
                'typ'         => (string) ($post['typ'] ?? ''),
                'firma'       => \trim((string) ($post['firma'] ?? '')),
                'zweck'       => (string) ($post['zweck'] ?? ''),
                // Nur wenn Mode 'fixed', senden wir die Daten aus Schritt 1 mit
                'datum_von' => $dateMode === 'fixed' ? (string) ($post['datum_von'] ?? '') : '',
                'datum_bis' => $dateMode === 'fixed' ? (string) ($post['datum_bis'] ?? '') : '',
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
                $maxUses,
                $custom, // Weitergabe an Service
                $expiresAt ?: null, // In null wandeln wenn leer
                $dateMode,
            );

            return "Gutschein erstellt: <strong>$code</strong>";
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }

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
        $tplKey = (string) ($post['template_key'] ?? 'std.7');

        // --- BACKEND SECURITY CHECK ---
        if (! $this->auth->hasPermission("template.$tplKey")) {
            return "Fehler: Sie haben keine Berechtigung, den Typ '$tplKey' manuell auszustellen.";
        }

        // Manuelle Buchung (Kostenlos/Bar)
        try {
            $post['status'] = 'bezahlt';
            if (isset($post['reason'])) {
                $post['internerKommentar'] = $post['reason'];
            }

            $permit = $this->permitService->createPermit($post, true);

            return "Manuelle Genehmigung erstellt: <strong>{$permit->code}</strong>";
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
        // Gutschein sperren / aktivieren
        $status = $post['action'] === 'activate_voucher' ? 'aktiv' : 'deaktiviert';
        $this->permitService->getVoucherService()->toggleStatus((string) $post['code'], $status);

        return 'Gutschein wurde ' . ($status === 'aktiv' ? 'reaktiviert.' : 'gesperrt.');
    }

    /**
     * Setzt den Sperrstatus (Suspension) einer Genehmigung.
     * Kontext: Interaktion mit PermitService::toggleSuspension().
     */
    private function actionToggleSuspension(array $post): string
    {
        // Genehmigung entsperren
        $suspended = $post['action'] === 'suspend_permit';
        $this->permitService->toggleSuspension((string) $post['code'], $suspended, (string) ($post['reason'] ?? ''));

        return 'Genehmigung wurde ' . ($suspended ? 'gesperrt.' : 'freigegeben.');
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
        if (isset($get['action']) && $get['action'] === 'print' && isset($get['code'])) {
            $permit = $this->storage->findByHash((string) $get['code']);
            if ($permit instanceof Permit) {
                /** @var Config $config */
                $config = $this->config;
                $this->render('admin_print_view', [
                    'permit'        => $permit,
                    'settings'      => $this->getSettingsArray(),
                    'config'        => $config,
                    'appRoot'       => $config->get('root_path'),
                    'opening'       => $this->holidayService->getGeneralOpeningHoursText(),
                    'holidayNotice' => $this->holidayService->getHolidaysInRangeText($permit->validity->von, $permit->validity->bis),
                ]);

                return true;
            }
        }

        return false;
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
        $filterStart = (string) ($get['start'] ?? \date('Y-01-01'));
        $filterEnd   = (string) ($get['end'] ?? \date('Y-12-31'));

        // Den Typ-Filter aus der URL auslesen (Standard: 'all')
        $filterType = (string) ($get['type'] ?? 'all');

        $allPermits = $this->storage->getAll();

        // Vorlagen laden, um den Typ abzugleichen
        $permitTemplates = $this->config->get('permit_templates', []);

        // 1. Filterung für den gewählten Zeitraum & Typ
        $filtered = \array_filter($allPermits, function (Permit $p) use ($filterStart, $filterEnd, $filterType, $permitTemplates): bool {
            $date = $p->erstellt->format('Y-m-d');

            // Check Zeitraum
            if ($date < $filterStart || $date > $filterEnd) {
                return false;
            }

            // Check Typ (wenn nicht 'all')
            if ($filterType !== 'all') {
                $tplType = $permitTemplates[$p->templateKey]['type'] ?? 'standard';
                if ($tplType !== $filterType) {
                    return false;
                }
            }

            return true;
        });

        // Export abfangen
        if (isset($get['export'])) {
            $this->handleExport((string) $get['export'], $filtered, $filterStart, $filterEnd);
            exit; // Wichtig: Nach dem Download darf kein HTML mehr gesendet werden!
        }

        // 2. Jährliche Gruppierung
        $yearlyStats = [];
        $vConfig     = $this->config->get('vehicle_types', []);

        foreach ($allPermits as $p) {
            $year = $p->erstellt->format('Y');
            if (! isset($yearlyStats[$year])) {
                $yearlyStats[$year] = [
                    'count'  => 0,
                    'paid'   => 0.0,
                    'unpaid' => 0.0,
                    'types'  => \array_fill_keys(\array_keys($vConfig), 0), // Dynamische Typen-Liste
                ];
                $yearlyStats[$year]['types']['__legacy__'] = 0; // Legacy-Support pro Jahr
            }
            ++$yearlyStats[$year]['count'];

            // Dynamisches Zählen des Fahrzeugtyps
            $pType = $p->vehicle->typ;
            if (isset($yearlyStats[$year]['types'][$pType])) {
                ++$yearlyStats[$year]['types'][$pType];
            } else {
                ++$yearlyStats[$year]['types']['__legacy__'];
            }

            if (\strtolower($p->status->current) === 'bezahlt') {
                $yearlyStats[$year]['paid'] += $p->validity->preisSnapshot;
            } else {
                $yearlyStats[$year]['unpaid'] += $p->validity->preisSnapshot;
            }
        }
        \krsort($yearlyStats); // Neueste Jahre zuerst

        // Gefilterte Daten ans Template übergeben
        // Hier wurde vorher $allPermits übergeben. Jetzt übergeben wir die $filtered Liste!
        $this->render('admin_dashboard', [
            'structure'        => $this->config->get('structure', []),
            'periodStats'      => $this->calculateDetailedStats($filtered),
            'yearlyStats'      => $yearlyStats,
            'permitGroups'     => $this->groupPermits($filtered), // <-- BUGFIX! GEÄNDERT von $allPermits und groups
            'settings'         => $this->getSettingsArray(),
            'auth'             => $this->auth,
            'message'          => $message,
            'filterStart'      => $filterStart,
            'filterEnd'        => $filterEnd,
            'filterType'       => $filterType, // An die Control-Bar übergeben
            'config'           => $this->config,
            'appRoot'          => $this->config->get('root_path'),
            'vouchers'         => $this->permitService->getVoucherService()->loadVouchers(),
            'voucherService'   => $this->permitService->getVoucherService(),
            'mailLogs'         => $this->mailService->loadLogs(),
            'permitService'    => $this->permitService,
            'migrationService' => $this->migrationService,
        ]);
    }

    /**
     * Berechnet detaillierte Finanz- und Nutzertyp-Statistiken für eine Liste von Genehmigungen.
     *
     * Finanz-KPIs (Revenue) und Parzellen-Ranking
     * Aggregiert Daten aus Permit-Array. Nutzt uasort zur Sortierung nach Plot-Ranking.
     *
     * @param Permit[] $permits
     *
     * @return array<string, mixed> Statistik-Array.
     */
    private function calculateDetailedStats(array $permits): array
    {
        $vConfig = $this->config->get('vehicle_types', []);

        // Initialisierung inklusive Legacy-Speicher
        // Initialisiert das Array mit allen Keys aus der Config (pkw, lkw, entsorg, etc.)
        $typeStats = \array_fill_keys(\array_keys($vConfig), 0);

        // NEU: Ein Sammelbecken für gelöschte Typen
        $typeStats['__legacy__'] = 0;

        $stats = [
            'count'          => \count($permits),
            'revenue_paid'   => 0.0,
            'revenue_unpaid' => 0.0,
            'types'          => $typeStats, // JETZT DYNAMISCH
            'plots'          => [],
        ];

        foreach ($permits as $p) {
            $pType = $p->vehicle->typ;

            // Wenn Typ existiert, normal zählen, sonst in den Legacy-Topf
            if (isset($stats['types'][$pType])) {
                ++$stats['types'][$pType];
            } else {
                // Falls der Typ aus der Config gelöscht wurde,
                // zählen wir ihn hier rein, damit die Summe stimmt.
                ++$stats['types']['__legacy__'];
            }

            $pNum = $p->owner->parzelle;

            // Initialisiere Parzelle im Ranking, falls noch nicht vorhanden
            if (! isset($stats['plots'][$pNum])) {
                $stats['plots'][$pNum] = [
                    'count'   => 0,
                    'revenue' => 0.0,
                    'email'   => '',
                    'name'    => '',
                ];
            }

            // Daten aggregieren
            ++$stats['plots'][$pNum]['count'];
            $stats['plots'][$pNum]['revenue'] += $p->validity->preisSnapshot;

            // Zuletzt verwendete Daten speichern
            $stats['plots'][$pNum]['name']  = $p->owner->name;
            $stats['plots'][$pNum]['email'] = $p->owner->email;

            if (\strtolower($p->status->current) === 'bezahlt') {
                $stats['revenue_paid'] += $p->validity->preisSnapshot;
            } else {
                $stats['revenue_unpaid'] += $p->validity->preisSnapshot;
            }
        }
        \uasort($stats['plots'], fn ($a, $b) => $b['count'] === $a['count'] ? $b['revenue'] <=> $a['revenue'] : $b['count'] <=> $a['count']);

        return $stats;
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
        // FIX: Wir nutzen den Vereinsnamen aus der Config für den Dateinamen (statt hartkodiert "kga")
        $slug = \strtolower(\preg_replace('/[^A-Za-z0-9]/', '_', (string) $this->config->get('vereins_name', 'export')));

        // FIX: Die Endung wird jetzt dynamisch über $format angehängt
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
                ], ';');

                foreach ($filtered as $permit) {
                    \fputcsv($output, [
                        $permit->code,
                        $permit->owner->name,
                        $permit->owner->email,
                        $permit->owner->parzelle,
                        $settings['vehicle_types'][$permit->vehicle->typ] ?? $permit->vehicle->typ,
                        $permit->vehicle->kennzeichen,
                        $permit->vehicle->firma ?? '',
                        $permit->validity->zweck,
                        \number_format($permit->validity->preisSnapshot, 2, ',', ''),
                        \strtoupper($permit->status->current),
                        $permit->erstellt->format('d.m.Y H:i'),
                    ], ';');
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
     * Berechnet zusammenfassende Statistiken für einen gefilterten Datensatz.
     *
     * Reduziert Permits auf Gesamtanzahl und Umsatz.
     *
     * @param array<int, Permit> $filtered
     *
     * @return array<string, mixed>
     */
    private function calculateStats(array $filtered): array
    {
        $stats = [
            'total_count'   => \count($filtered),
            'total_revenue' => \array_reduce(
                $filtered,
                fn (float $sum, Permit $permit): float => $sum + $permit->validity->preisSnapshot,
                0.0,
            ),
            'types' => ['pkw' => 0, 'lkw' => 0],
            'plots' => [],
        ];

        foreach ($filtered as $permit) {
            $stats['types'][$permit->vehicle->typ]    = ($stats['types'][$permit->vehicle->typ] ?? 0) + 1;
            $stats['plots'][$permit->owner->parzelle] = ($stats['plots'][$permit->owner->parzelle] ?? 0) + 1;
        }
        \arsort($stats['plots']);

        return $stats;
    }

    /**
     * Gruppiert Genehmigungen nach ihrem aktuellen Gültigkeitsstatus (aktiv/future/expired/unpaid).
     *
     * Logik-Kern für die tabellarische Übersicht.
     *
     * @param array<int, Permit> $allPermits
     *
     * @return array<string, array<Permit>>
     */
    private function groupPermits(array $allPermits): array
    {
        $now    = new \DateTimeImmutable('today');
        $groups = [
            'active'  => [],
            'future'  => [],
            'expired' => [],
            'unpaid'  => [],
        ];

        foreach ($allPermits as $permit) {
            // 1. FINANZ-LOGIK (Unbezahlte sammeln)
            // Wir prüfen auf 'bezahlt'. Alles andere (offen, leer, NULL)
            // gilt als "unbezahlt" und landet im Finanz-Tab.
            if (\strtolower(\trim($permit->status->current)) !== 'bezahlt') {
                $groups['unpaid'][] = $permit;
            }

            // Zeit-Logik
            if ($permit->validity->bis < $now) {
                $groups['expired'][] = $permit;

                continue;
            }
            if ($permit->validity->von > $now) {
                $groups['future'][] = $permit;

                continue;
            }
            $groups['active'][] = $permit;
        }

        // 3. SORTIERUNG FÜR FINANZEN
        // Die neuesten Anträge (erstellt am) sollen oben stehen.
        \usort($groups['unpaid'], fn ($permitEntryA, $permitEntryB) => $permitEntryB->erstellt <=> $permitEntryA->erstellt);

        return $groups;
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
            'opening_hours'      => $this->config->get('opening_hours'),
            'jahresFarbe'        => $this->config->get('jahresFarbe'),
            'base_url'           => $this->config->getBaseUrl(),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
        ];
    }

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

        // NEU: Dynamische Sicherheitsprüfung basierend auf der Baumstruktur!
        // Prüft exakt das Recht, z.B. 'dashboard.migration.users.json_to_mysql'
        if (! $this->auth->hasPermission("dashboard.migration.{$target}.{$direction}")) {
            return 'Fehler: Sie haben keine Berechtigung für diese Migrations-Aktion.';
        }

        return $this->migrationService->execute($target, $direction);
    }

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
        /** @var Config $config */
        $config = $this->config;
        // Sicherstellen, dass appRoot für das Template immer auf einem Slash endet:
        $appRoot = \rtrim((string) $config->get('root_path'), '/\\');

        // Wir fügen auth global hinzu, falls es mal vergessen wird
        if (! isset($data['auth'])) {
            $data['auth'] = $this->auth;
        }

        // Macht aus ['stats' => $stats] echte Variablen im lokalen Scope
        \extract($data);
        // IMPORTANT: Hier muss ein / zwischen $appRoot und templates
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }

    /**
     * Führt eine System-Wiederherstellung (Restore) aus deinem Backup durch.
     * Sicherheitsprüfung über hasPermission und Level-Check.
     *
     * @param array<string, mixed> $post
     *
     * @return string Ergebnis der Restore-Aktion.
     */
    private function actionRestoreData(array $post): string
    {
        // Die Permission-Sperre bleibt (sie deckt den Dev-Admin via '*' oder 'sys_' automatisch mit ab)
        if (! $this->auth->hasPermission('dashboard.migration.restore.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, eine System-Wiederherstellung durchzuführen.';
        }

        // Der alte getLevel() Check wurde hier komplett entfernt, da das Rechtesystem
        // völlig ausreicht. Wer das Recht hat, darf auch restoren.

        $target    = (string) ($post['target'] ?? '');
        $timestamp = (string) ($post['timestamp'] ?? '');

        if ($target === '' || $timestamp === '') {
            return 'Fehler: Unvollständige Angaben für Restore.';
        }

        return $this->migrationService->restore($timestamp, $target);
    }
}
