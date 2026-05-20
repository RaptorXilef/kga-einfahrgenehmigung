<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Application/AdminController.php

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
 * Zuständig für: Authentifizierung, Dashboard-Rendering, Finanz-Export,
 * Gutschein-Verwaltung und System-Wartung (Migration/Restore).
 * Kontext: Einstiegspunkt für Admin-Anfragen. Verwaltet den gesamten Admin-Lifecycle.
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
     * Haupt-Einstiegspunkt für alle Admin-Requests.
     * Orchestriert: Authentifizierung -> Wartungs-Checks -> POST-Aktionen -> Rendering.
     * Kontext: PRG-Pattern (Post-Redirect-Get) wird hier angewendet.
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
     * Behandelt Login-Versuche und Logout-Aktionen.
     * Kontext: Gibt true zurück, wenn ein Redirect erfolgt ist (Request beenden).
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     *
     * @return bool True wenn Request gestoppt werden soll.
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
                \header('Location: admin.php');

                return true;
            }
        }

        return false;
    }

    /**
     * Zentrale Weiche für alle POST-Aktionen (z.B. Gutscheine, Status-Änderungen).
     * Kontext: Nutzt 'match' für sauberes Routing. Leitet an action-Methoden weiter.
     *
     * @param array<string, mixed> $post
     *
     * @return string Nachricht für die UI.
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
     * Markiert eine Genehmigung als bezahlt im Storage.
     * Kontext: Nutzt PermitService::manualActivate().
     */
    private function actionMarkAsPaid(array $post): string
    {
        $code = (string) ($post['code'] ?? '');

        return $this->permitService->manualActivate($code) ? "Zahlung für $code bestätigt." : '';
    }

    /**
     * Erstellt einen Gutschein über VoucherService.
     * Kontext: Beinhaltet Sicherheitsprüfung (hasPermission). Übergibt diverse Gutschein-Parameter.
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
                'datum_von' => ($dateMode === 'fixed') ? (string) ($post['datum_von'] ?? '') : '',
                'datum_bis' => ($dateMode === 'fixed') ? (string) ($post['datum_bis'] ?? '') : '',
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
     * Manuelle Erstellung einer Genehmigung ohne Online-Zahlung.
     * Kontext: Erzwingt 'status' = 'bezahlt' und nutzt PermitService::createPermit().
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
     * Ändert den aktiven/deaktivierten Status eines Gutscheins.
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
     * Fängt Print-Requests für Genehmigungen ab.
     * Kontext: Überprüft Zugriff und rendert 'admin_print_view'.
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
                    'permit'   => $permit,
                    'settings' => $this->getSettingsArray(),
                    'config'   => $config,
                    'appRoot'  => $config->get('root_path'),
                    'opening'  => $this->holidayService->getGeneralOpeningHoursText(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * /**
     * Rendert das Admin-Dashboard mit Statistiken und Tabellen.
     * Kontext: Berechnet YearlyStats, ruft calculateDetailedStats auf, verarbeitet Exporte und übergibt diverse Services.
     *
     * @param array<string, mixed> $get
     */
    private function renderDashboard(array $get, string $message): void
    {
        $filterStart = (string) ($get['start'] ?? \date('Y-01-01'));
        $filterEnd   = (string) ($get['end'] ?? \date('Y-12-31'));
        $allPermits  = $this->storage->getAll();

        // 1. Filterung für den gewählten Zeitraum
        $filtered = \array_filter($allPermits, function (Permit $p) use ($filterStart, $filterEnd): bool {
            $date = $p->erstellt->format('Y-m-d');

            return $date >= $filterStart && $date <= $filterEnd;
        });

        // --- FIX ANFRAGE 3: EXPORT-ROUTING ---
        // Bevor HTML gerendert wird, fangen wir den Export-Befehl ab!
        if (isset($get['export'])) {
            $this->handleExport((string) $get['export'], $filtered, $filterStart, $filterEnd);
            exit; // Wichtig: Nach dem Download darf kein HTML mehr gesendet werden!
        }

        // 2. Jährliche Gruppierung (bleibt wie sie ist für die Historie)
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

        // --- FIX ANFRAGE 2: FILTER-LOGIK ---
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
     * Berechnet Finanz-KPIs (Revenue) und Parzellen-Ranking.
     * Kontext: Aggregiert Daten aus Permit-Array. Nutzt uasort zur Sortierung nach Plot-Ranking.
     *
     * @param Permit[] $permits
     *
     * @return array<string, mixed>
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
        \uasort($stats['plots'], fn ($a, $b) => ($b['count'] === $a['count']) ? $b['revenue'] <=> $a['revenue'] : $b['count'] <=> $a['count']);

        return $stats;
    }

    /**
     * Erzeugt CSV- oder JSON-Dateiexporte.
     * Kontext: Setzt Header und UTF-8-BOM für Excel-Kompatibilität.
     *
     * @param Permit[] $filtered
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

        if ($format === 'json') {
            \header('Content-Type: application/json');
            \header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo \json_encode(\array_values($filtered), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Berechnet zusammenfassende Statistiken für einen gefilterten Datensatz.
     * Kontext: Reduziert Permits auf Gesamtanzahl und Umsatz.
     *
     * @param Permit[] $filtered
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
     * Gruppiert Permits für die Dashboard-Tabs (Active, Future, Expired, Unpaid).
     * Kontext: Logik-Kern für die tabellarische Übersicht.
     *
     * @param Permit[] $allPermits
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
            // Wir prüfen auf 'bezahlt'. Alles andere (offen, wartend, leer, NULL)
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
        \usort($groups['unpaid'], function ($permitEntryA, $permitEntryB) {
            return $permitEntryB->erstellt <=> $permitEntryA->erstellt;
        });

        return $groups;
    }

    /**
     * Baut das Konfigurations-Array für Templates.
     * Kontext: Schnittstelle zwischen Config-Objekt und UI.
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
     * Trigger für Datenmigrationen (Sync SQL/JSON).
     * Kontext: Level-0 Sicherheitscheck (Dev-Admin only).
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
     * Rendert das PHTML-Template und stellt Variablen zur Verfügung.
     * Kontext: Nutzt 'extract' um Array-Keys zu lokalen Variablen zu machen.
     *
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
     * Führt eine Wiederherstellung aus einem Backup durch.
     * Kontext: Sicherheitsprüfung über hasPermission und Level-Check.
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
