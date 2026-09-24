<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ExportFinanceData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use PDO;

/**
 * Ersetzt den alten ExportService und PermitFilterService!
 * Sammelt die Export-Daten blitzschnell via nativen PDO-Queries.
 *
 * @implements QueryHandlerInterface<ExportFinanceDataQuery, FinanceExportResultDto>
 */
final readonly class ExportFinanceDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock, // <--- Injiziert
    ) {
    }

    public function handle(mixed $query): FinanceExportResultDto
    {
        $rows = $this->fetchFilteredData($query);
        $filename = $this->generateFilename($query->format, $query->start, $query->end);

        if ($query->format === 'json') {
            return new FinanceExportResultDto(
                content: $this->generateJson($rows),
                filename: $filename,
                contentType: 'application/json',
            );
        }

        if ($query->format === 'csv_stats') {
            return new FinanceExportResultDto(
                content: $this->generateStatsCsv($rows),
                filename: $filename,
                contentType: 'text/csv; charset=utf-8',
            );
        }

        return new FinanceExportResultDto(
            content: $this->generateCsv($rows),
            filename: $filename,
            contentType: 'text/csv; charset=utf-8',
        );
    }

    private function fetchFilteredData(ExportFinanceDataQuery $query): array
    {
        $validTplKeys = [];
        $permitTemplates = $this->config->get('permit_templates', []);

        if ($query->type !== 'all') {
            foreach ($permitTemplates as $k => $tpl) {
                if (!(($tpl['type'] ?? 'standard') === $query->type)) {
                    continue;
                }

                $validTplKeys[] = $k;
            }
            if ($validTplKeys === []) {
                return [];
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
        $stmt->execute(\array_merge($binds, $binds)); // Binds für beide Tabellen

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function generateCsv(array $rows): string
    {
        $output = \fopen('php://temp', 'r+');
        if (!$output) {
            return '';
        }

        \fwrite($output, "\xEF\xBB\xBF");
        \fputcsv($output, [
            'Belegdatum', 'Belegnummer', 'Pächter/Name', 'Parzelle',
            'Buchungstext', 'Betrag (EUR)', 'Zahlungsstatus', 'Tarif/Vorlage',
        ], ';', '"', '\\');

        foreach ($rows as $row) {
            $dateStr = \substr($row['bezahlt_am'] ?: $row['erstellt'], 0, 10);
            $dt = new DateTimeImmutable($dateStr); // Sicherer als strtotime
            $belegDate = $dt->format('d.m.Y');

            $zweck = $this->sanitizeCsvCell($row['zweck'] . ' (Kfz: ' . $row['kennzeichen'] . ')');

            \fputcsv($output, [
                $belegDate,
                $row['code'],
                $this->sanitizeCsvCell($row['name']),
                \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                $zweck,
                \number_format((float) $row['preis'], 2, ',', ''),
                \strtoupper($row['status']),
                $row['template_key'],
            ], ';', '"', '\\');
        }

        \rewind($output);
        $content = \stream_get_contents($output);
        \fclose($output);

        return (string) $content;
    }

    private function generateStatsCsv(array $rows): string
    {
        $output = \fopen('php://temp', 'r+');
        if (!$output) {
            return '';
        }

        \fwrite($output, "\xEF\xBB\xBF");

        $stats = [
            'gesamtzahl' => \count($rows),
            'bezahlt' => 0, 'offen' => 0, 'storniert' => 0,
            'erwartet' => 0.0, 'bezahlt_umsatz' => 0.0, 'offen_umsatz' => 0.0,
            'vorlagen' => [],
        ];

        foreach ($rows as $row) {
            $status = $row['status'];
            $price = (float) $row['preis'];
            $tpl = $row['template_key'];

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

        \fputcsv($output, ['Metrik', 'Wert'], ';', '"', '\\');
        \fputcsv($output, ['Gesamtzahl Anträge', $stats['gesamtzahl']], ';', '"', '\\');
        \fputcsv($output, ['Status: Bezahlt', $stats['bezahlt']], ';', '"', '\\');
        \fputcsv($output, ['Status: Offen', $stats['offen']], ';', '"', '\\');
        \fputcsv($output, ['Status: Storniert', $stats['storniert']], ';', '"', '\\');
        \fputcsv($output, ['Erwarteter Umsatz (EUR)', \number_format($stats['erwartet'], 2, ',', '')], ';', '"', '\\');
        \fputcsv($output, ['Bezahlter Umsatz (EUR)', \number_format($stats['bezahlt_umsatz'], 2, ',', '')], ';', '"', '\\');
        \fputcsv($output, ['Offener Umsatz (EUR)', \number_format($stats['offen_umsatz'], 2, ',', '')], ';', '"', '\\');

        foreach ($stats['vorlagen'] as $tpl => $count) {
            \fputcsv($output, ['Nutzung Vorlage: ' . $tpl, $count], ';', '"', '\\');
        }

        \rewind($output);
        $content = \stream_get_contents($output);
        \fclose($output);

        return (string) $content;
    }

    private function generateJson(array $rows): string
    {
        $stats = [
            'gesamtzahl_antraege' => \count($rows),
            'status_uebersicht' => ['bezahlt' => 0, 'offen' => 0, 'storniert' => 0],
            'finanzen' => ['erwarteter_umsatz_eur' => 0.0, 'bezahlter_umsatz_eur' => 0.0, 'offener_umsatz_eur' => 0.0],
            'vorlagen_nutzung' => [],
        ];

        $transactions = [];

        foreach ($rows as $row) {
            $status = $row['status'];
            $price = (float) $row['preis'];
            $tpl = $row['template_key'];

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
        $clubName = (string) $this->config->get('vereins_name', 'export');
        $clubName = \mb_strtolower($clubName, 'UTF-8');
        $clubName = \str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $clubName);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '_', $clubName), '_');

        $timestamp = $this->clock->now()->format('Ymd_Hi'); // Time-Safe via ClockInterface
        $extension = $format === 'csv_stats' ? 'csv' : $format;
        $type = $format === 'csv_stats' ? 'statistik' : 'finanzexport';

        return "{$slug}_{$type}_{$start}_bis_{$end}_{$timestamp}.{$extension}";
    }

    private function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) $value;
        if ($str === '') {
            return $str;
        }
        if (\in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }
}
