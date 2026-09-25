<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure;

use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use App\Modules\Finance\Application\DTO\UnpaidPermitsDto;
use Override;
use PDO;

/**
 * Die Infrastruktur darf auf die Tabellen übergreifend zugreifen,
 * aber liefert der Application-Schicht nur saubere DTOs.
 */
final readonly class PdoUnpaidPermitProvider implements UnpaidPermitProviderInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    #[Override]
    public function getPermitDataForImport(): UnpaidPermitsDto
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

        return new UnpaidPermitsDto(
            allCodes: $allCodes,
            unpaidCodes: $unpaidCodes,
            unpaidPlates: $unpaidPlates,
            prices: $prices,
        );
    }
}
