<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetBankImportPermitData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Exception;
use Override;
use PDO;

/**
 * Liest alle aktiven, stornierten und kürzlich archivierten Genehmigungen speicherschonend via PDO für den Bankabgleich aus.
 *
 * @implements QueryHandlerInterface<GetBankImportPermitDataQuery, array{
 *   allCodes: array<string, bool>,
 *   unpaidCodes: array<string, string>,
 *   unpaidPlates: array<string, string>,
 *   prices: array<string, float>,
 *   records: array<string, array{
 *     code: string,
 *     shortCode: string,
 *     name: string,
 *     parzelle: int,
 *     plotFormatted: string,
 *     kennzeichen: string,
 *     typ: string,
 *     preis: float,
 *     status: string,
 *     vonFormatted: string,
 *     bisFormatted: string,
 *     erstelltFormatted: string,
 *     bezahltAmFormatted: ?string,
 *     isSuspended: bool
 *   }>
 * }>
 */
final readonly class GetBankImportPermitDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetBankImportPermitDataQuery $query
     *
     * @return array{
     *   allCodes: array<string, bool>,
     *   unpaidCodes: array<string, string>,
     *   unpaidPlates: array<string, string>,
     *   prices: array<string, float>,
     *   records: array<string, array{
     *     code: string,
     *     shortCode: string,
     *     name: string,
     *     parzelle: int,
     *     plotFormatted: string,
     *     kennzeichen: string,
     *     typ: string,
     *     preis: float,
     *     status: string,
     *     vonFormatted: string,
     *     bisFormatted: string,
     *     erstelltFormatted: string,
     *     bezahltAmFormatted: ?string,
     *     isSuspended: bool
     *   }>
     * }
     */
    #[Override]
    public function handle(QueryInterface $query): array
    {
        $storageConfig = $this->config->getArray('storage_config');
        $permitsTable = (string) ($storageConfig['permits']['table'] ?? 'permits');
        $cancelledTable = (string) ($storageConfig['permits_cancelled']['table'] ?? 'permits_cancelled');
        $archiveTable = (string) ($storageConfig['permits_archive']['table'] ?? 'permits_archive');

        $minYear = (int) $this->clock->now()->format('Y') - 1;

        $cols = 'code, name, kennzeichen, parzelle, typ, status, preis, von, bis, erstellt, bezahlt_am, is_suspended';
        $sql = "
            SELECT {$cols}, 'active' AS source_table FROM `{$permitsTable}`
            UNION ALL
            SELECT {$cols}, 'cancelled' AS source_table FROM `{$cancelledTable}`
            UNION ALL
            SELECT {$cols}, 'archive' AS source_table FROM `{$archiveTable}` WHERE YEAR(erstellt) >= :minYear
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['minYear' => $minYear]);

        $allCodes = [];
        $unpaidCodes = [];
        $unpaidPlates = [];
        $prices = [];
        $records = [];

        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $code = \strtoupper(\trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $parts = \explode('-', $code);
            $shortCode = \strtoupper((string) \end($parts));

            $status = \strtolower(\trim((string) ($row['status'] ?? 'offen')));
            if (($row['source_table'] ?? '') === 'cancelled') {
                $status = 'storniert';
            }

            $name = \trim((string) ($row['name'] ?? 'Unbekannt'));
            $parzelle = (int) ($row['parzelle'] ?? 0);
            $plotFormatted = $parzelle > 0 ? \str_pad((string) $parzelle, 4, '0', \STR_PAD_LEFT) : '----';
            $plate = \trim((string) ($row['kennzeichen'] ?? ''));
            $price = (float) ($row['preis'] ?? 0.0);

            $allCodes[$code] = true;
            $prices[$code] = $price;

            if ($status === 'offen' && ($row['source_table'] ?? '') === 'active') {
                $unpaidCodes[$code] = $name;
                $unpaidPlates[$code] = $plate;
            }

            $records[$code] = [
                'code' => $code,
                'shortCode' => $shortCode,
                'name' => $name,
                'parzelle' => $parzelle,
                'plotFormatted' => $plotFormatted,
                'kennzeichen' => $plate !== '' ? $plate : '---',
                'typ' => \strtoupper((string) ($row['typ'] ?? 'PKW')),
                'preis' => $price,
                'status' => $status,
                'vonFormatted' => $this->formatDateSafe((string) ($row['von'] ?? '')),
                'bisFormatted' => $this->formatDateSafe((string) ($row['bis'] ?? '')),
                'erstelltFormatted' => $this->formatDateSafe((string) ($row['erstellt'] ?? '')),
                'bezahltAmFormatted' => $this->formatOptionalDateSafe((string) ($row['bezahlt_am'] ?? '')),
                'isSuspended' => (bool) ($row['is_suspended'] ?? false),
            ];
        }

        return [
            'allCodes' => $allCodes,
            'unpaidCodes' => $unpaidCodes,
            'unpaidPlates' => $unpaidPlates,
            'prices' => $prices,
            'records' => $records,
        ];
    }

    private function formatDateSafe(string $raw): string
    {
        $trimmed = \trim($raw);
        if ($trimmed === '') {
            return '---';
        }

        try {
            return (new DateTimeImmutable($trimmed))->format('d.m.Y');
        } catch (Exception) {
            return $trimmed;
        }
    }

    private function formatOptionalDateSafe(string $raw): ?string
    {
        $trimmed = \trim($raw);
        if (\in_array($trimmed, ['', '0000-00-00', '0000-00-00 00:00:00', 'null'], true)) {
            return null;
        }

        try {
            return (new DateTimeImmutable($trimmed))->format('d.m.Y');
        } catch (Exception) {
            return null;
        }
    }
}
