<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Orchestriert die Admin-Logik für den Vorstand.
 *
 * Path: src/Application/AdminController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\MigrationService;
use App\Core\Service\PermitService;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;

/**
 * Orchestriert die Admin-Logik für den Vorstand.
 */
final readonly class AdminController
{
    public function __construct(
        private ConfigInterface $config,
        private AuthService $auth,
        private StorageInterface $storage,
        private PermitService $permitService,
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Haupt-Methode, die den Request verarbeitet.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        // 1. Authentifizierung & Globale Aktionen
        if ($this->handleAuthActions($get, $post)) {
            return; // Beendet den Request nach Redirect
        }

        // --- AUTH-GATEKEEPER ---
        if (! $this->auth->isLoggedIn()) {
            $this->render('admin_login', [
                'message'  => '',
                'settings' => $this->getSettingsArray(),
            ]);

            return;
        }

        // 2. Daten-Aktionen verarbeiten
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
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
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
     * Verarbeitet alle POST-Aktionen des Dashboards.
     *
     * @param array<string, mixed> $post
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

    private function actionMarkAsPaid(array $post): string
    {
        $code = (string) ($post['code'] ?? '');

        return $this->permitService->manualActivate($code) ? "Zahlung für $code bestätigt." : '';
    }

    private function actionCreateVoucher(array $post): string
    {
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
                (string) ($_SESSION['admin_user'] ?? 'Admin'),
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

    private function actionCreateManual(array $post): string
    {
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

    private function actionToggleVoucher(array $post): string
    {
        // Gutschein sperren / aktivieren
        $status = $post['action'] === 'activate_voucher' ? 'aktiv' : 'deaktiviert';
        $this->permitService->getVoucherService()->toggleStatus((string) $post['code'], $status);

        return 'Gutschein wurde ' . ($status === 'aktiv' ? 'reaktiviert.' : 'gesperrt.');
    }

    private function actionToggleSuspension(array $post): string
    {
        // Genehmigung entsperren
        $suspended = $post['action'] === 'suspend_permit';
        $this->permitService->toggleSuspension((string) $post['code'], $suspended, (string) ($post['reason'] ?? ''));

        return 'Genehmigung wurde ' . ($suspended ? 'gesperrt.' : 'freigegeben.');
    }

    /**
     * @param array<string, mixed> $get
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
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $get
     */
    private function renderDashboard(array $get, string $message): void
    {
        $filterStart = (string) ($get['start'] ?? \date('Y-01-01'));
        $filterEnd   = (string) ($get['end'] ?? \date('Y-12-31'));
        $allPermits  = $this->storage->getAll();

        /** @var Permit[] $filtered */
        $filtered = \array_filter($allPermits, function (Permit $permit) use ($filterStart, $filterEnd): bool {
            $date = $permit->erstellt->format('Y-m-d');

            return $date >= $filterStart && $date <= $filterEnd;
        });

        if (isset($get['export'])) {
            $this->handleExport((string) $get['export'], $filtered, $filterStart, $filterEnd);

            return;
        }

        // E-Mail Logs laden
        // TODO Datei korrekt einbinden über config!
        $logPath  = $this->config->get('root_path') . '/storage/mail_log.json';
        $mailLogs = \file_exists($logPath)
            ? \json_decode((string) \file_get_contents($logPath), true)
            : [];

        $voucherService = $this->permitService->getVoucherService();

        $this->render('admin_dashboard', [
            'stats'          => $this->calculateStats($filtered),
            'groups'         => $this->groupPermits($allPermits),
            'settings'       => $this->getSettingsArray(),
            'adminUser'      => $this->auth->getUsername(),
            'adminLevel'     => $this->auth->getLevel(),
            'message'        => $message,
            'filterStart'    => $filterStart,
            'filterEnd'      => $filterEnd,
            'config'         => $this->config,
            'appRoot'        => $this->config->get('root_path'),
            'vouchers'       => $voucherService->loadVouchers(),
            'voucherService' => $voucherService,
            'reasons'        => [
                'Bargeldzahlung vor Ort',
                'Vorstandsbeschluss',
                'Härtefall-Regelung',
                'Gartenarbeit-Kompensation',
            ],
            'mailLogs'      => $mailLogs,
            'permitService' => $this->permitService,
        ]);
    }

    /**
     * Export-Weiche (CSV / JSON).
     *
     * @param Permit[] $filtered
     */
    private function handleExport(string $format, array $filtered, string $start, string $end): void
    {
        $filename = "export_kga_{$start}_bis_{$end}";
        $settings = $this->getSettingsArray();

        if ($format === 'csv') {
            \header('Content-Type: text/csv; charset=utf-8');
            \header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
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
            \header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            echo \json_encode(\array_values($filtered), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Berechnet die Statistiken inklusive Typen und Top-Parzellen.
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
     * Gruppiert Genehmigungen nach Zeitstatus und Zahlungsstatus.
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
     * Hilfsmethode, um das $settings-Array für Templates zu bauen.
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

    // Neue Methode im AdminController
    private function actionMigrateData(array $post): string
    {
        // BUGFIX HIER: AuthService nutzen statt $_SESSION
        if ($this->auth->getLevel() !== 0) {
            return 'Fehler: Nur Superadmins dürfen migrieren.';
        }

        $direction = (string) ($post['direction'] ?? 'sync');
        $target    = (string) ($post['target'] ?? '');

        return $this->migrationService->execute($target, $direction);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data = []): void
    {
        /** @var Config $config */
        $config  = $this->config;
        $appRoot = (string) $config->get('root_path');

        // Macht aus ['stats' => $stats] echte Variablen im lokalen Scope
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
