<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Contracts;

/**
 * Entkoppelt das Finance-Modul von der Datenbankstruktur des Permit-Moduls.
 */
interface UnpaidPermitProviderInterface
{
    /**
     * @return array{
     *   allCodes: array<string, bool>,
     *   unpaidCodes: array<string, string>,
     *   unpaidPlates: array<string, string>,
     *   prices: array<string, float>
     * }
     */
    public function getPermitDataForImport(): array;
}
