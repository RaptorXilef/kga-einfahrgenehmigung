<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Core\Entity\Permit;
use App\Core\Entity\PermitStatus;

/**
 * Service für die Erstellung von Finanz- und Statistik-Exporten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ExportService
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    /**
     * Generiert eine Kassensoftware-kompatible CSV-Datei (DATEV-Style).
     *
     * @param Permit[] $filteredPermits
     */
    public function generateCsv(array $filteredPermits): string
    {
        $output = \fopen('php://temp', 'r+');
        if (!$output) {
            return '';
        }

        // UTF-8 BOM erzwingen, damit Excel Umlaute korrekt darstellt
        \fwrite($output, "\xEF\xBB\xBF");

        // Kassensoftware / Buchhaltungs-freundliche Header
        \fputcsv($output, [
            'Belegdatum',
            'Belegnummer',
            'Pächter/Name',
            'Parzelle',
            'Buchungstext',
            'Betrag (EUR)',
            'Zahlungsstatus',
            'Tarif/Vorlage',
        ], ';', '"', '\\');

        foreach ($filteredPermits as $permit) {
            // Buchhaltungslogik: Belegdatum ist das Zahlungsdatum, falls vorhanden. Sonst Antragsdatum.
            $dateObj = $permit->bezahlt_am ?? $permit->getCreatedAt();

            $row = [
                $dateObj->format('d.m.Y'),
                $permit->code->value,
                $this->sanitizeCsvCell($permit->getOwnerName()),
                $permit->getPlotNumber(),
                $this->sanitizeCsvCell($permit->getPurpose() . ' (Kfz: ' . $permit->getLicensePlate() . ')'),
                \number_format($permit->getPrice(), 2, ',', ''), // Deutsches Format für Kassensoftware (Komma)
                \strtoupper($permit->getStatus()->value),
                $permit->template_key->value,
            ];

            \fputcsv($output, $row, ';', '"', '\\');
        }

        \rewind($output);
        $csvContent = \stream_get_contents($output);
        \fclose($output);

        return (string) $csvContent;
    }

    /**
     * Generiert ein reichhaltiges JSON inkl. detaillierter Buchhaltungs-Statistiken.
     *
     * @param Permit[] $filteredPermits
     */
    public function generateJson(array $filteredPermits): string
    {
        $stats = [
            'gesamtzahl_antraege' => \count($filteredPermits),
            'status_uebersicht' => [
                'bezahlt' => 0,
                'offen' => 0,
                'storniert' => 0,
            ],
            'finanzen' => [
                'erwarteter_umsatz_eur' => 0.0,
                'bezahlter_umsatz_eur' => 0.0,
                'offener_umsatz_eur' => 0.0,
            ],
            'vorlagen_nutzung' => [],
        ];

        $transactions = [];

        foreach ($filteredPermits as $p) {
            $status = $p->getStatus()->value;
            $price = $p->getPrice();
            $tpl = $p->template_key->value;

            // 1. Zähler und Finanzen berechnen
            if ($status === PermitStatus::Bezahlt->value) {
                ++$stats['status_uebersicht']['bezahlt'];
                $stats['finanzen']['bezahlter_umsatz_eur'] += $price;
            } elseif ($status === PermitStatus::Offen->value) {
                ++$stats['status_uebersicht']['offen'];
                $stats['finanzen']['offener_umsatz_eur'] += $price;
            } elseif ($status === PermitStatus::Storniert->value) {
                ++$stats['status_uebersicht']['storniert'];
            }

            if ($status !== PermitStatus::Storniert->value) {
                $stats['finanzen']['erwarteter_umsatz_eur'] += $price;
            }

            // 2. Beliebtheit der Vorlagen zählen
            if (!isset($stats['vorlagen_nutzung'][$tpl])) {
                $stats['vorlagen_nutzung'][$tpl] = 0;
            }
            ++$stats['vorlagen_nutzung'][$tpl];

            // 3. Flache Transaktionsdaten (Bypass der Value Objects für sauberes JSON)
            $transactions[] = [
                'code' => $p->code->value,
                'belegdatum' => ($p->bezahlt_am ?? $p->getCreatedAt())->format('Y-m-d H:i:s'),
                'name' => $p->getOwnerName(),
                'parzelle' => $p->getPlotNumber(),
                'buchungstext' => $p->getPurpose() . ' (Kfz: ' . $p->getLicensePlate() . ')',
                'betrag' => $price,
                'status' => $status,
                'vorlage' => $tpl,
            ];
        }

        // Floats sauber auf 2 Dezimalstellen runden
        $stats['finanzen']['erwarteter_umsatz_eur'] = \round($stats['finanzen']['erwarteter_umsatz_eur'], 2);
        $stats['finanzen']['bezahlter_umsatz_eur'] = \round($stats['finanzen']['bezahlter_umsatz_eur'], 2);
        $stats['finanzen']['offener_umsatz_eur'] = \round($stats['finanzen']['offener_umsatz_eur'], 2);

        $payload = [
            'statistiken' => $stats,
            'transaktionen' => $transactions,
        ];

        return \json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function generateFilename(string $format, string $start, string $end): string
    {
        $slug = \strtolower((string) \preg_replace('/[^A-Za-z0-9]/', '_', (string) $this->config->get('vereins_name', 'export')));
        $timestamp = \date('Ymd_Hi');

        return "{$slug}_finanzexport_{$start}_bis_{$end}_{$timestamp}.{$format}";
    }

    /**
     * Schützt vor CSV-Injection / Formula-Injection (Excel Makro Viren).
     */
    private function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) $value;
        if ($str === '') {
            return $str;
        }

        $firstChar = $str[0];
        if (\in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }
}
