<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\LicensePlate;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicensePlate::class)]
final class LicensePlateTest extends TestCase
{
    #[Test]
    #[DataProvider('validPlateProvider')]
    public function itAcceptsAndTrimsValidLicensePlates(string $input, string $expected): void
    {
        $plate = new LicensePlate($input);
        $this->assertSame($expected, $plate->value);
    }

    public static function validPlateProvider(): Iterator
    {
        yield 'standard plate' => ['B-ML 1234', 'B-ML 1234'];
        yield 'lowercase' => ['b-xx 99', 'B-XX 99'];
        yield 'with spaces' => ['  HD-AB 123  ', 'HD-AB 123'];
    }

    #[Test]
    #[DataProvider('normalizedPlateProvider')]
    public function itNormalizesLicensePlatesForSafeComparisons(string $input, string $normalized): void
    {
        $plate = new LicensePlate($input);
        $this->assertSame($normalized, $plate->getNormalized());
    }

    public static function normalizedPlateProvider(): Iterator
    {
        yield 'standard plate' => ['B-ML 1234', 'BML1234'];
        yield 'multiple spaces' => ['B  ML  1234', 'BML1234'];
        yield 'special chars' => ['B:ML_1234!', 'BML1234'];
        yield 'anonymized fallback' => ['XXX-XX 9999', 'XXXXX9999'];
    }

    #[Test]
    public function itCorrectlyComparesTwoLicensePlatesRegardlessOfFormatting(): void
    {
        $plate1 = new LicensePlate('B-ML 1234');
        $plate2 = new LicensePlate('B ML 1234');
        $plate3 = new LicensePlate('B-ML-1234');
        $plate4 = new LicensePlate('B-ML 9999');

        $this->assertTrue($plate1->equals($plate2));
        $this->assertTrue($plate1->equals($plate3));
        $this->assertFalse($plate1->equals($plate4));
    }

    #[Test]
    public function itThrowsExceptionForEmptyLicensePlates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Das Kennzeichen darf nicht leer sein.');

        new LicensePlate('   ');
    }
}
