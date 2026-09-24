<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ExportPermits;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use PDO;

/**
 * Sammelt die Export-Daten der Permits via nativen PDO-Queries.
 *
 * @implements QueryHandlerInterface<ExportPermitsQuery, ExportPermitsResultDto>
 */
final readonly class ExportPermitsHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    public function handle(mixed $query): ExportPermitsResultDto
    {
        $rows = $this->fetchFilteredData($query);
        $filename = $this->generateFilename($query->state, $query->start, $query->end);

        return new ExportPermitsResultDto(
            content: $this->generateCsv($rows),
            filename: $filename,
            contentType: 'text/csv; charset=utf-8',
        );
    }

    private function fetchFilteredData(ExportPermitsQuery $query): array
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

        $whereParts = [];
        $binds = [];

        // Zeitfilter überspringen, wenn 'all' angefordert wird
        if ($query->state !== 'all') {
            $whereParts[] = 'DATE(erstellt) >= ? AND DATE(erstellt) <= ?';
            $binds = [$query->start, $query->end];
        }

        if ($validTplKeys !== []) {
            $in = \str_repeat('?,', \count($validTplKeys) - 1) . '?';
            $whereParts[] = "template_key IN ($in)";
            $binds = \array_merge($binds, $validTplKeys);
        }

        if ($query->searchQuery !== '') {
            $whereParts[] = "CONCAT_WS(' ', code, name, IFNULL(email, ''), kennzeichen, LPAD(parzelle, 4, '0'), zweck) LIKE ?";
            $binds[] = '\%' . \strtolower(\trim($query->searchQuery)) . '%';
        }

        $whereStr = empty($whereParts) ? '1=1' : \implode(' AND ', $whereParts);
        $cols = 'parzelle, kennzeichen, code, name, von, bis';

        // State Condition (Zusatzfilter für Aktiv/Future/Expired)
        $stateCond = match ($query->state) {
            'active' => "bis >= CURDATE() AND von <= CURDATE() AND status != 'storniert'",
            'future' => "von > CURDATE() AND status != 'storniert'",
            'expired' => "bis < CURDATE() AND status != 'storniert'",
            'active_future' => "bis >= CURDATE() AND status != 'storniert'",
            default => '1=1' // all
        };

        $orderBy = match ($query->state) {
            'expired' => 'ORDER BY bis DESC, parzelle ASC',
            'all' => 'ORDER BY erstellt DESC',
            default => 'ORDER BY von ASC, parzelle ASC' // active, future, active_future
        };

        $sql = "
            SELECT {$cols}, erstellt FROM permits WHERE {$whereStr} AND {$stateCond}
            UNION ALL
            SELECT {$cols}, erstellt FROM permits_archive WHERE {$whereStr} AND {$stateCond}
            {$orderBy}
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
            'Parzelle', 'Kennzeichen', 'Code', 'Name', 'Datum gültig von', 'Datum gültig bis',
        ], ';', '"', '\\');

        foreach ($rows as $row) {
            $dtVon = new DateTimeImmutable($row['von']);
            $dtBis = new DateTimeImmutable($row['bis']);

            \fputcsv($output, [
                \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT), $this->sanitizeCsvCell($row['kennzeichen']), $row['code'],
                $this->sanitizeCsvCell($row['name']),
                $dtVon->format('d.m.Y'), $dtBis->format('d.m.Y'),
            ], ';', '"', '\\');
        }

        \rewind($output);
        $content = \stream_get_contents($output);
        \fclose($output);

        return (string) $content;
    }

    private function generateFilename(string $state, string $start, string $end): string
    {
        $clubName = (string) $this->config->get('vereins_name', 'export');
        $clubName = \mb_strtolower($clubName, 'UTF-8');
        $clubName = \str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $clubName);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '_', $clubName), '_');

        $timestamp = $this->clock->now()->format('Ymd_Hi');

        $stateSlug = match ($state) {
            'active_future' => 'aktiv_und_zukunft',
            'future' => 'zukunft',
            'expired' => 'abgelaufen',
            'all' => 'alle_ohne_zeitfilter',
            default => 'aktiv'
        };

        if ($state === 'all') {
            return "{$slug}_genehmigungen_{$stateSlug}_{$timestamp}.csv";
        }

        return "{$slug}_genehmigungen_{$stateSlug}_{$start}_bis_{$end}_{$timestamp}.csv";
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
