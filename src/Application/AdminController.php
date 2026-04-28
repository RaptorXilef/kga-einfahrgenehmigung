<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * @file src/Application/AdminController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\PermitService;
use App\Infrastructure\Auth\AuthService;

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
        $message = '';

        // --- 1. GLOBAL ACTIONS ---
        if (isset($get['action']) && $get['action'] === 'logout') {
            $this->auth->logout();
            \header('Location: admin.php');
            exit;
        }

        if (isset($post['login'])) {
            if ($this->auth->login((string) ($post['user'] ?? ''), (string) ($post['pass'] ?? ''))) {
                \header('Location: admin.php');
                exit;
            }
            if (! $this->auth->isLoggedIn()) {
                $message = 'Ungültige Anmeldedaten.';
            }
        }

        // --- AUTH-GATEKEEPER ---
        if (! $this->auth->isLoggedIn()) {
            // FIX: Auch hier die render-Methode nutzen!
            $this->render('admin_login', [
                'message'  => $message,
                'settings' => $this->getSettingsArray(),
            ]);
            exit;
        }

        // Variablen einmal definieren
        $adminUser  = (string) ($_SESSION['admin_user'] ?? 'Admin');
        $adminLevel = (int) ($_SESSION['admin_level'] ?? 1);

        // --- 2. PRINT PREVIEW ---
        if (isset($get['action']) && $get['action'] === 'print' && isset($get['code'])) {
            $permit = $this->storage->findByHash((string) $get['code']);
            if ($permit instanceof Permit) {
                // FIX: render() statt include nutzen!
                $this->render('admin_print_view', [
                    'permit'   => $permit,
                    'settings' => $this->getSettingsArray(),
                ]);
                exit;
            }
            $message = 'Genehmigung nicht gefunden.';
        }

        // --- 3. LOGGED-IN ACTIONS ---

        // Zahlung markieren (Level 1 & 2)
        if (
            isset($post['action']) && $post['action'] === 'mark_as_paid'
            && $this->permitService->manualActivate((string) ($post['code'] ?? ''))
        ) {
            $message = 'Zahlung für ' . (string) ($post['code'] ?? '') . ' bestätigt.';
        }

        // --- 4. FILTER & EXPORT ---
        $filterStart = (string) ($get['start'] ?? \date('Y-01-01'));
        $filterEnd   = (string) ($get['end'] ?? \date('Y-12-31'));
        $allPermits  = $this->storage->getAll();

        // Statistik-Filter (Basierend auf Erstellungsdatum)
        /** @var Permit[] $filtered */
        $filtered = \array_filter($allPermits, function (Permit $permit) use ($filterStart, $filterEnd): bool {
            $date = $permit->erstellt?->format('Y-m-d') ?? '';

            return $date >= $filterStart && $date <= $filterEnd;
        });

        // Export-Logik
        if (isset($get['export'])) {
            $this->handleExport((string) $get['export'], $filtered, $filterStart, $filterEnd);
            exit;
        }

        // --- 5. STATISTIKEN & GRUPPIERUNG ---
        // FIX: Wir übergeben die oben definierten Variablen $adminUser und $adminLevel!
        $this->render('admin_dashboard', [
            'stats'      => $this->calculateStats($filtered),
            'groups'     => $this->groupPermits($allPermits),
            'settings'   => $this->getSettingsArray(),
            'adminUser'  => $adminUser, // Hier wird die Variable jetzt "gelesen"
            'adminLevel' => $adminLevel, // Hier wird die Variable jetzt "gelesen"
            'message'    => $message,
            'config'     => $this->config,
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
                        $permit->erstellt?->format('d.m.Y H:i') ?? '',
                    ], ';', '"', '\\');
                }
                \fclose($output);
            }
            exit;
        }

        if ($format === 'json') {
            \header('Content-Type: application/json');
            \header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            echo \json_encode(\array_values($filtered), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Berechnet die Statistiken inklusive Typen und Top-Parzellen.
     *
     * @param Permit[] $filtered
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
     */
    private function groupPermits(array $allPermits): array
    {
        $now    = new \DateTimeImmutable('today');
        $groups = ['active' => [], 'future' => [], 'expired' => []];
        foreach ($allPermits as $permit) {
            if ($permit->bis < $now) {
                $groups['expired'][] = $permit;
            } elseif ($permit->von > $now) {
                $groups['future'][] = $permit;
            } else {
                $groups['active'][] = $permit;
            }
        }

        return $groups;
    }

    /**
     * Hilfsmethode, um das $settings-Array für Templates zu bauen.
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

    private function render(string $templatePath, array $data = []): void
    {
        /** @var string $appRoot */
        $appRoot = $this->config->get('root_path');

        // Macht aus ['stats' => $stats] echte Variablen im lokalen Scope
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
