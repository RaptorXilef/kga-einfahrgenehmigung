<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ExportPermits;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\CsvExporterInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Generator;
use Override;
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
        private CsvExporterInterface $csvExporter,
    ) {
    }

    /**
     * @param ExportPermitsQuery $query
     */
    #[Override]
    public function handle(mixed $query): ExportPermitsResultDto
    {
        // 1. Data-Stream anstoßen (Generator)
        $rowStream = $this->yieldFilteredData($query);

        // 2. Stream direkt in den CSV Puffer schreiben (Memory Safe)
        $csvContent = $this->generateCsvFromStream($rowStream);

        $filename = $this->generateFilename($query->state, $query->start, $query->end);

        return new ExportPermitsResultDto(
            content: $csvContent,
            filename: $filename,
            contentType: 'text/csv; charset=utf-8',
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function yieldFilteredData(ExportPermitsQuery $query): Generator
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
                return; // Beendet den Generator sofort
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
            $binds[] = '%' . \strtolower(\trim($query->searchQuery)) . '%';
        }

        $whereStr = $whereParts === [] ? '1=1' : \implode(' AND ', $whereParts);
        $cols = 'parzelle, kennzeichen, code, name, von, bis';

        // State Condition (Zusatzfilter für Aktiv/Future/Expired)
        $stateCond = match ($query->state) {
            'active' => "bis >= CURDATE() AND von <= CURDATE() AND status != 'storniert'",
            'future' => "von > CURDATE() AND status != 'storniert'",
            'expired' => "bis < CURDATE() AND status != 'storniert'",
            'active_future' => "bis >= CURDATE() AND status != 'storniert'",
            default => '1=1'
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

        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            yield $row;
        }
    }

    /**
     * @param iterable<int, array<string, mixed>> $rowStream
     */
    private function generateCsvFromStream(iterable $rowStream): string
    {
        $headers = [
            'Parzelle', 'Kennzeichen', 'Code', 'Name', 'Datum gültig von', 'Datum gültig bis',
        ];

        $mappedRows = (function () use ($rowStream): Generator {
            foreach ($rowStream as $row) {
                $dtVon = new DateTimeImmutable((string) $row['von']);
                $dtBis = new DateTimeImmutable((string) $row['bis']);

                yield [
                    \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT),
                    (string) $row['kennzeichen'],
                    (string) $row['code'],
                    (string) $row['name'],
                    $dtVon->format('d.m.Y'),
                    $dtBis->format('d.m.Y'),
                ];
            }
        })();

        return $this->csvExporter->export($headers, $mappedRows);
    }

    private function generateFilename(string $state, string $start, string $end): string
    {
        $clubName = $this->config->getString('vereins_name', 'export');
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
}
