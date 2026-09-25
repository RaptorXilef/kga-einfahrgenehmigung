<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Permit-Modul (Bounded Context Kommunikation).
 */
interface PermitIntegrationInterface
{
    public function hasPermits(string $email): bool;

    /**
     * Anonymisiert abgelaufene Archiv-Einträge, die älter als der angegebene Schwellenwert (in Jahren) sind.
     */
    public function anonymizeArchive(int $yearsThreshold = 10): int;

    /**
     * Liefert alle für den Bankabgleich relevanten Code-Mappings aus dem Permit-Bestand.
     *
     * @return array{
     *   allCodes: array<string, bool>,
     *   unpaidCodes: array<string, string>,
     *   unpaidPlates: array<string, string>,
     *   prices: array<string, float>
     * }
     */
    public function getPermitDataForBankImport(): array;

    /**
     * Streamt alle buchhaltungsrelevanten Genehmigungszeilen (Aktiv + Archiv) speicherschonend für den Finanz-Export.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function yieldPermitsForFinanceExport(
        string $start,
        string $end,
        string $type,
        string $searchQuery,
    ): iterable;
}
