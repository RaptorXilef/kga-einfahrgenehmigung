<?php

declare(strict_types=1);

namespace App\Core\Utils;

/**
 * Zustandslose Hilfsklasse für mathematische Datums- und Zeit-Operationen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class DateRangeHelper
{
    /**
     * Prüft mathematisch, ob sich zwei Datumszeiträume überschneiden.
     *
     * @param \DateTimeImmutable $startA Start des ersten Zeitraums
     * @param \DateTimeImmutable $endA   Ende des ersten Zeitraums
     * @param \DateTimeImmutable $startB Start des zweiten Zeitraums
     * @param \DateTimeImmutable $endB   Ende des zweiten Zeitraums
     *
     * @return bool True, wenn eine zeitliche Überschneidung vorliegt.
     */
    public static function overlaps(
        \DateTimeImmutable $startA,
        \DateTimeImmutable $endA,
        \DateTimeImmutable $startB,
        \DateTimeImmutable $endB,
    ): bool {
        return $startA <= $endB && $endA >= $startB;
    }
}
