<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure;

use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use PDO;

/**
 * Die Infrastruktur darf auf die Tabellen übergreifend zugreifen,
 * aber liefert der Application-Schicht nur saubere Arrays.
 */
final readonly class PdoUnpaidPermitProvider implements UnpaidPermitProviderInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getPermitDataForImport(): array
    {
        $stmt = $this->pdo->query('SELECT code, name, kennzeichen, status, preis FROM permits');
        $allCodes = [];
        $unpaidCodes = [];
        $unpaidPlates = [];
        $prices = [];

        // VSA FIX: Nutze fetch() statt fetchAll(), um bei vielen Pächtern den RAM zu schonen
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $c = $row['code'];
            $allCodes[$c] = true;
            $prices[$c] = (float) $row['preis'];

            if ($row['status'] === 'bezahlt') {
                continue;
            }

            $unpaidCodes[$c] = $row['name'];
            $unpaidPlates[$c] = $row['kennzeichen'];
        }

        return [
            'allCodes' => $allCodes,
            'unpaidCodes' => $unpaidCodes,
            'unpaidPlates' => $unpaidPlates,
            'prices' => $prices,
        ];
    }
}
