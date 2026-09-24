<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Contracts;

use App\Modules\Finance\Application\DTO\UnpaidPermitsDto;

/**
 * Entkoppelt das Finance-Modul von der Datenbankstruktur des Permit-Moduls.
 */
interface UnpaidPermitProviderInterface
{
    /**
     * Liefert alle für den Bankabgleich relevanten Code-Mappings als striktes DTO.
     */
    public function getPermitDataForImport(): UnpaidPermitsDto;
}
