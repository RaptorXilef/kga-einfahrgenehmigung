<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ExportFinanceData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\PermitIntegrationInterface;
use App\Contracts\System\CsvExporterInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Generator;
use Override;

/**
 * Sammelt die Export-Daten modulsicher über das PermitIntegrationInterface (ohne Fremd-Tabellen-SQL).
 *
 * @implements QueryHandlerInterface<ExportFinanceDataQuery, FinanceExportResultDto>
 */
final readonly class ExportFinanceDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PermitIntegrationInterface $permitIntegration,
        private ConfigInterface $config,
        private ClockInterface $clock,
        private CsvExporterInterface $csvExporter,
    ) {
    }

    /**
     * @param ExportFinanceDataQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): FinanceExportResultDto
    {
        $rowStream = $this->permitIntegration->yieldPermitsForFinanceExport(
            $query->start,
            $query->end,
            $query->type,
            $query->searchQuery,
        );
        $filename = $this->generateFilename($query->format, $query->start, $query->end);

        if ($query->format === 'json') {
            return new FinanceExportResultDto(
                content: $this->generateJson($rowStream),
                filename: $filename,
                contentType: 'application/json',
            );
        }

        if ($query->format === 'csv_stats') {
            return new FinanceExportResultDto(
                content: $this->generateStatsCsv($rowStream),
                filename: $filename,
                contentType: 'text/csv; charset=utf-8',
            );
        }

        return new FinanceExportResultDto(
            content: $this->generateCsv($rowStream),
            filename: $filename,
            contentType: 'text/csv; charset=utf-8',
        );
    }

    /**
     * @param iterable<int, array<string, mixed>> $rowStream
     */
    private function generateCsv(iterable $rowStream): string
    {
        $headers = [
            'Belegdatum', 'Belegnummer', 'Pächter/Name', 'Parzelle',
            'Buchungstext', 'Betrag (EUR)', 'Zahlungsstatus', 'Tarif/Vorlage',
        ];

        $mappedRows = (function () use ($rowStream): Generator {
            foreach ($rowStream as $row) {
                $dateStr = \substr((string) ($row['bezahlt_am'] ?: $row['erstellt']), 0, 10);
                $dt = new DateTimeImmutable($dateStr);
                $belegDate = $dt->format('d.m.Y');

                $zweck = $row['zweck'] . ' (Kfz: ' . $row['kennzeichen'] . ')';

                yield [
                    $belegDate,
                    (string) $row['code'],
                    (string) $row['name'],
                    \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                    $zweck,
                    \number_format((float) $row['preis'], 2, ',', ''),
                    \strtoupper((string) $row['status']),
                    (string) $row['template_key'],
                ];
            }
        })();

        return $this->csvExporter->export($headers, $mappedRows);
    }

    /**
     * @param iterable<int, array<string, mixed>> $rowStream
     */
    private function generateStatsCsv(iterable $rowStream): string
    {
        $stats = [
            'gesamtzahl' => 0,
            'bezahlt' => 0, 'offen' => 0, 'storniert' => 0,
            'erwartet' => 0.0, 'bezahlt_umsatz' => 0.0, 'offen_umsatz' => 0.0,
            'vorlagen' => [],
        ];

        foreach ($rowStream as $row) {
            $status = (string) $row['status'];
            $price = (float) $row['preis'];
            $tpl = (string) $row['template_key'];

            ++$stats['gesamtzahl'];

            if ($status === 'bezahlt') {
                ++$stats['bezahlt'];
                $stats['bezahlt_umsatz'] += $price;
            } elseif ($status === 'offen') {
                ++$stats['offen'];
                $stats['offen_umsatz'] += $price;
            } elseif ($status === 'storniert') {
                ++$stats['storniert'];
            }

            if ($status !== 'storniert') {
                $stats['erwartet'] += $price;
            }

            $stats['vorlagen'][$tpl] ??= 0;
            ++$stats['vorlagen'][$tpl];
        }

        $summaryRows = [
            ['Gesamtzahl Anträge', $stats['gesamtzahl']],
            ['Status: Bezahlt', $stats['bezahlt']],
            ['Status: Offen', $stats['offen']],
            ['Status: Storniert', $stats['storniert']],
            ['Erwarteter Umsatz (EUR)', \number_format($stats['erwartet'], 2, ',', '')],
            ['Bezahlter Umsatz (EUR)', \number_format($stats['bezahlt_umsatz'], 2, ',', '')],
            ['Offener Umsatz (EUR)', \number_format($stats['offen_umsatz'], 2, ',', '')],
        ];

        foreach ($stats['vorlagen'] as $tpl => $count) {
            $summaryRows[] = ['Nutzung Vorlage: ' . $tpl, $count];
        }

        return $this->csvExporter->export(['Metrik', 'Wert'], $summaryRows);
    }

    /**
     * @param iterable<int, array<string, mixed>> $rowStream
     */
    private function generateJson(iterable $rowStream): string
    {
        $stats = [
            'gesamtzahl_antraege' => 0,
            'status_uebersicht' => ['bezahlt' => 0, 'offen' => 0, 'storniert' => 0],
            'finanzen' => ['erwarteter_umsatz_eur' => 0.0, 'bezahlter_umsatz_eur' => 0.0, 'offener_umsatz_eur' => 0.0],
            'vorlagen_nutzung' => [],
        ];

        $transactions = [];

        foreach ($rowStream as $row) {
            $status = (string) $row['status'];
            $price = (float) $row['preis'];
            $tpl = (string) $row['template_key'];

            ++$stats['gesamtzahl_antraege'];

            if ($status === 'bezahlt') {
                ++$stats['status_uebersicht']['bezahlt'];
                $stats['finanzen']['bezahlter_umsatz_eur'] += $price;
            } elseif ($status === 'offen') {
                ++$stats['status_uebersicht']['offen'];
                $stats['finanzen']['offener_umsatz_eur'] += $price;
            } elseif ($status === 'storniert') {
                ++$stats['status_uebersicht']['storniert'];
            }

            if ($status !== 'storniert') {
                $stats['finanzen']['erwarteter_umsatz_eur'] += $price;
            }

            $stats['vorlagen_nutzung'][$tpl] ??= 0;
            ++$stats['vorlagen_nutzung'][$tpl];

            $transactions[] = [
                'code' => $row['code'],
                'belegdatum' => $row['bezahlt_am'] ?: $row['erstellt'],
                'name' => $row['name'],
                'parzelle' => \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                'buchungstext' => $row['zweck'] . ' (Kfz: ' . $row['kennzeichen'] . ')',
                'betrag' => $price,
                'status' => $status,
                'vorlage' => $tpl,
            ];
        }

        $stats['finanzen']['erwarteter_umsatz_eur'] = \round($stats['finanzen']['erwarteter_umsatz_eur'], 2);
        $stats['finanzen']['bezahlter_umsatz_eur'] = \round($stats['finanzen']['bezahlter_umsatz_eur'], 2);
        $stats['finanzen']['offener_umsatz_eur'] = \round($stats['finanzen']['offener_umsatz_eur'], 2);

        return \json_encode(['statistiken' => $stats, 'transaktionen' => $transactions], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function generateFilename(string $format, string $start, string $end): string
    {
        $clubName = $this->config->getString('vereins_name', 'export');
        $clubName = \mb_strtolower($clubName, 'UTF-8');
        $clubName = \str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $clubName);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '_', $clubName), '_');

        $timestamp = $this->clock->now()->format('Ymd_Hi');
        $extension = $format === 'csv_stats' ? 'csv' : $format;
        $type = $format === 'csv_stats' ? 'statistik' : 'finanzexport';

        return "{$slug}_{$type}_{$start}_bis_{$end}_{$timestamp}.{$extension}";
    }
}
