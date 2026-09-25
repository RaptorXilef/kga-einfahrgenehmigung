<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ExportFinanceData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\CsvExporterInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Generator;
use Override;
use PDO;

/**
 * Sammelt die Export-Daten blitzschnell via nativen PDO-Queries.
 *
 * @implements QueryHandlerInterface<ExportFinanceDataQuery, FinanceExportResultDto>
 */
final readonly class ExportFinanceDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
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
        // Den Generator anwerfen (es werden noch keine Daten aus MySQL geladen)
        $rowStream = $this->yieldFilteredData($query);
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
     * @return Generator<int, array<string, mixed>>
     */
    private function yieldFilteredData(ExportFinanceDataQuery $query): Generator
    {
        $validTplKeys = [];
        $permitTemplates = $this->config->getArray('permit_templates');

        if ($query->type !== 'all') {
            foreach ($permitTemplates as $k => $tpl) {
                if (!(($tpl['type'] ?? 'standard') === $query->type)) {
                    continue;
                }

                $validTplKeys[] = $k;
            }
            if ($validTplKeys === []) {
                return;
            }
        }

        $whereParts = ['DATE(erstellt) >= ? AND DATE(erstellt) <= ?'];
        $binds = [$query->start, $query->end];

        if ($validTplKeys !== []) {
            $in = \str_repeat('?,', \count($validTplKeys) - 1) . '?';
            $whereParts[] = "template_key IN ($in)";
            $binds = \array_merge($binds, $validTplKeys);
        }

        if ($query->searchQuery !== '') {
            $whereParts[] = "CONCAT_WS(' ', code, name, IFNULL(email, ''), kennzeichen, LPAD(parzelle, 4, '0'), zweck) LIKE ?";
            $binds[] = '%' . \strtolower(\trim($query->searchQuery)) . '%';
        }

        $whereStr = \implode(' AND ', $whereParts);
        $cols = 'code, template_key, name, parzelle, kennzeichen, zweck, preis, status, erstellt, bezahlt_am';

        $sql = "
            SELECT {$cols} FROM permits WHERE {$whereStr}
            UNION ALL
            SELECT {$cols} FROM permits_archive WHERE {$whereStr}
            ORDER BY erstellt ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(\array_merge($binds, $binds));

        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            yield $row;
        }
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

        // Wir aggregieren den Stream, speichern die Einzelzeilen aber nicht zwischen!
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

        // Bei JSON müssen wir leider ein Array aufbauen, da json_encode() keinen Stream akzeptiert.
        // Dennoch sparen wir massiv RAM, da PDO die Zeilen nun abräumt.
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
