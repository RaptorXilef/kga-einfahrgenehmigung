<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Orchestriert die Admin-Logik für den Vorstand.
 *
 * @file src/Application/AdminController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
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

        // 2. Daten-Aktionen (Markieren)
        // $get entfernt, da es in handleDataActions nicht gebraucht wird
        $message = $this->handleDataActions($post);

        // 3. Print Preview abfangen
        if ($this->shouldStopRequest($get)) {
            return;
        }

        // 4. View-Logik (Dashboard & Export)
        $this->renderDashboard($get, $message);
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
     * @param array<string, mixed> $post
     */
    private function handleDataActions(array $post): string
    {
        // 1. Bestehende Logik: Zahlung markieren
        if (isset($post['action']) && $post['action'] === 'mark_as_paid') {
            $code = (string) ($post['code'] ?? '');
            if ($this->permitService->manualActivate($code)) {
                return 'Zahlung für ' . $code . ' bestätigt.';
            }
        }

        // 2. Gutschein erstellen
        if (isset($post['action']) && $post['action'] === 'create_voucher') {
            // FIX für PHPCS: Ungenutzte Variable $vs gelöscht
            $reason = (string) ($post['reason'] === 'other' ? $post['custom_reason'] : $post['reason']);
            $code   = $this->permitService->getVoucherService()->createVoucher(
                $reason,
                (string) $_SESSION['admin_user'],
            );

            return "Gutschein erstellt: <strong>{$code}</strong> (Grund: {$reason})";
        }

        // 3. Manuelle Buchung (Kostenlos/Bar)
        if (isset($post['action']) && $post['action'] === 'create_manual') {
            $post['status']            = 'bezahlt'; // Direkt freigeschaltet
            $post['internerKommentar'] = (string) ($post['reason'] === 'other'
                ? $post['custom_reason']
                : $post['reason']);

            try {
                $permit = $this->permitService->createPermit($post, true);

                return "Manuelle Genehmigung erstellt: <strong>{$permit->code}</strong>";
            } catch (\Exception $e) {
                return 'Fehler: ' . $e->getMessage();
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $get
     */
    private function shouldStopRequest(array $get): bool
    {
        if (isset($get['action']) && $get['action'] === 'print' && isset($get['code'])) {
            $permit = $this->storage->findByHash((string) $get['code']);
            if ($permit instanceof Permit) {
                $this->render('admin_print_view', [
                    'permit'   => $permit,
                    'settings' => $this->getSettingsArray(),
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
            // FIX: nullsafe entfernt, da erstellt non-nullable ist
            $date = $permit->erstellt->format('Y-m-d');

            return $date >= $filterStart && $date <= $filterEnd;
        });

        if (isset($get['export'])) {
            $this->handleExport((string) $get['export'], $filtered, $filterStart, $filterEnd);

            return;
        }

        // E-Mail Logs laden
        $logPath  = $this->config->get('root_path') . '/storage/mail_log.json';
        $mailLogs = \file_exists($logPath)
            ? \json_decode((string) \file_get_contents($logPath), true)
            : [];

        $voucherService = $this->permitService->getVoucherService();

        $this->render('admin_dashboard', [
            'stats'       => $this->calculateStats($filtered),
            'groups'      => $this->groupPermits($allPermits),
            'settings'    => $this->getSettingsArray(),
            'adminUser'   => (string) ($_SESSION['admin_user'] ?? 'Admin'),
            'adminLevel'  => (int) ($_SESSION['admin_level'] ?? 1),
            'message'     => $message,
            'filterStart' => $filterStart,
            'filterEnd'   => $filterEnd,
            'config'      => $this->config, // WICHTIG für den Indikator
            'appRoot'     => $this->config->get('root_path'), // WICHTIG für Includes
            'vouchers'    => $voucherService->loadVouchers(),
            'reasons'     => [
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
                \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF)); // BOM

                // Hinzufügen der Parameter für Umschließung und Escape (verhindert Warning)
                \fputcsv($output, [
                    'Kennung',
                    'Name',
                    'E-Mail',
                    'Parzelle',
                    'Typ',
                    'Kennzeichen',
                    'Firma/Lieferant',
                    'Zweck',
                    'Einnahme (€)',
                    'Status',
                    'Erstellt am',
                ], ';', '"', '\\');

                foreach ($filtered as $permit) {
                    \fputcsv($output, [
                        $permit->code,
                        $permit->name,
                        $permit->email,
                        $permit->parzelle,
                        $settings['vehicle_types'][$permit->typ] ?? $permit->typ,
                        $permit->kennzeichen,
                        $permit->firma ?? '',
                        $settings['purposes'][$permit->zweck] ?? $permit->zweck,
                        \number_format($permit->preisSnapshot, 2, ',', ''),
                        \strtoupper((string) $permit->status),
                        $permit->erstellt->format('d.m.Y H:i') ?? '',
                    ], ';', '"', '\\');
                }
                \fclose($output);
            }

            return;
        }

        if ($format !== 'json') {
            return;
        }

        \header('Content-Type: application/json');
        \header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo \json_encode(\array_values($filtered), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
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
                fn (float $sum, Permit $permit): float => $sum + $permit->preisSnapshot,
                0.0,
            ),
            'types' => ['pkw' => 0, 'lkw' => 0],
            'plots' => [],
        ];

        foreach ($filtered as $permit) {
            $stats['types'][$permit->typ]      = ($stats['types'][$permit->typ] ?? 0) + 1;
            $stats['plots'][$permit->parzelle] = ($stats['plots'][$permit->parzelle] ?? 0) + 1;
        }
        \arsort($stats['plots']);

        return $stats;
    }

    /**
     * Gruppiert Genehmigungen nach Zeitstatus.
     *
     * @param Permit[] $allPermits
     *
     * @return array<string, array<Permit>>
     */
    private function groupPermits(array $allPermits): array
    {
        $now    = new \DateTimeImmutable('today');
        $groups = ['active' => [], 'future' => [], 'expired' => []];
        foreach ($allPermits as $permit) {
            // Early Return / Flache Logik statt ELSE
            if ($permit->bis < $now) {
                $groups['expired'][] = $permit;

                continue;
            }
            if ($permit->von > $now) {
                $groups['future'][] = $permit;

                continue;
            }
            $groups['active'][] = $permit;
        }

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
