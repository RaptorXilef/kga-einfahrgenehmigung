<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetBankImportPermitData;

use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use Override;
use PDO;

/**
 * Liest alle aktiven Genehmigungs-Codes, offenen Beträge und Kennzeichen speicherschonend via PDO für den Bankabgleich aus.
 *
 * @implements QueryHandlerInterface<GetBankImportPermitDataQuery, array{
 *   allCodes: array<string, bool>,
 *   unpaidCodes: array<string, string>,
 *   unpaidPlates: array<string, string>,
 *   prices: array<string, float>
 * }>
 */
final readonly class GetBankImportPermitDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @param GetBankImportPermitDataQuery $query
     *
     * @return array{
     *   allCodes: array<string, bool>,
     *   unpaidCodes: array<string, string>,
     *   unpaidPlates: array<string, string>,
     *   prices: array<string, float>
     * }
     */
    #[Override]
    public function handle(QueryInterface $query): array
    {
        $stmt = $this->pdo->query('SELECT code, name, kennzeichen, status, preis FROM permits');
        $allCodes = [];
        $unpaidCodes = [];
        $unpaidPlates = [];
        $prices = [];

        if ($stmt !== false) {
            while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $c = (string) $row['code'];
                $allCodes[$c] = true;
                $prices[$c] = (float) $row['preis'];

                if ($row['status'] === 'bezahlt') {
                    continue;
                }

                $unpaidCodes[$c] = (string) $row['name'];
                $unpaidPlates[$c] = (string) $row['kennzeichen'];
            }
        }

        return [
            'allCodes' => $allCodes,
            'unpaidCodes' => $unpaidCodes,
            'unpaidPlates' => $unpaidPlates,
            'prices' => $prices,
        ];
    }
}
