<?php
declare(strict_types=1);
namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\LicensePlate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicensePlate::class)]
final class LicensePlateTest extends TestCase
{
    #[Test]
    #[DataProvider('validPlateProvider')]
    public function it_accepts_and_trims_valid_license_plates(string $input, string $expected): void
    {
        $plate = new LicensePlate($input);
        self::assertSame($expected, $plate->value);
    }

    public static function validPlateProvider(): array
    {
        return [
            'standard plate' => ['B-ML 1234', 'B-ML 1234'],
            'lowercase' => ['b-xx 99', 'B-XX 99'],
            'with spaces' => ['  HD-AB 123  ', 'HD-AB 123'],
        ];
    }

    #[Test]
    #[DataProvider('normalizedPlateProvider')]
    public function it_normalizes_license_plates_for_safe_comparisons(string $input, string $normalized): void
    {
        $plate = new LicensePlate($input);
        self::assertSame($normalized, $plate->getNormalized());
    }

    public static function normalizedPlateProvider(): array
    {
        return [
            'standard plate' => ['B-ML 1234', 'BML1234'],
            'multiple spaces' => ['B  ML  1234', 'BML1234'],
            'special chars' => ['B:ML_1234!', 'BML1234'],
            'anonymized fallback' => ['XXX-XX 9999', 'XXXXX9999'],
        ];
    }

    #[Test]
    public function it_correctly_compares_two_license_plates_regardless_of_formatting(): void
    {
        $plate1 = new LicensePlate('B-ML 1234');
        $plate2 = new LicensePlate('B ML 1234');
        $plate3 = new LicensePlate('B-ML-1234');
        $plate4 = new LicensePlate('B-ML 9999');

        self::assertTrue($plate1->equals($plate2));
        self::assertTrue($plate1->equals($plate3));
        self::assertFalse($plate1->equals($plate4));
    }

    #[Test]
    public function it_throws_exception_for_empty_license_plates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Das Kennzeichen darf nicht leer sein.');

        new LicensePlate('   ');
    }
}
