<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Domain;

use App\Modules\Permit\Domain\DateRangeHelper;
use DateTimeImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateRangeHelper::class)]
final class DateRangeHelperTest extends TestCase
{
    #[Test]
    #[DataProvider('dateRangeProvider')]
    public function itCalculatesDateOverlapsCorrectly(
        string $startA,
        string $endA,
        string $startB,
        string $endB,
        bool $expectedOverlap,
    ): void {
        $dtStartA = new DateTimeImmutable($startA);
        $dtEndA = new DateTimeImmutable($endA);
        $dtStartB = new DateTimeImmutable($startB);
        $dtEndB = new DateTimeImmutable($endB);

        $this->assertSame(
            $expectedOverlap,
            DateRangeHelper::overlaps($dtStartA, $dtEndA, $dtStartB, $dtEndB),
        );
    }

    public static function dateRangeProvider(): Iterator
    {
        // Zeitraum A: 10. bis 20. Oktober
        $aStart = '2026-10-10';
        $aEnd = '2026-10-20';

        yield 'B is completely before A' => [$aStart, $aEnd, '2026-10-01', '2026-10-09', false];
        yield 'B is completely after A' => [$aStart, $aEnd, '2026-10-21', '2026-10-30', false];
        yield 'B overlaps start of A' => [$aStart, $aEnd, '2026-10-05', '2026-10-15', true];
        yield 'B overlaps end of A' => [$aStart, $aEnd, '2026-10-15', '2026-10-25', true];
        yield 'B is entirely inside A' => [$aStart, $aEnd, '2026-10-12', '2026-10-18', true];
        yield 'A is entirely inside B' => [$aStart, $aEnd, '2026-10-01', '2026-10-31', true];
        yield 'Exact match' => [$aStart, $aEnd, $aStart, $aEnd, true];

        // Grenzfälle: Ein Tag Überschneidung
        yield 'B ends exactly when A starts' => [$aStart, $aEnd, '2026-10-01', '2026-10-10', true];
        yield 'B starts exactly when A ends' => [$aStart, $aEnd, '2026-10-20', '2026-10-30', true];
    }
}
