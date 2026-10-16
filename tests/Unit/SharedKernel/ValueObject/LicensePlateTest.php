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
    public function itAcceptsTrimsAndFormatsValidLicensePlates(string $input, string $expected): void
    {
        $plate = new LicensePlate($input);

        $this->assertSame($expected, $plate->value);
        $this->assertSame($expected, (string) $plate);
    }

    public static function validPlateProvider(): Iterator
    {
        yield 'standard plate' => ['B-ML 1234', 'B-ML 1234'];
        yield 'lowercase' => ['b-xx 99', 'B-XX 99'];
        yield 'with spaces' => ['  HD-AB 123  ', 'HD-AB 123'];
        yield 'state plate formatting' => ['M-AB1234', 'M-AB 1234'];
        yield 'auto format berlin' => ['BXX123', 'B-XX 123'];
        yield 'auto format normal' => ['HDAB123', 'HD-AB 123'];
        yield 'auto format 3 letters' => ['BARAB123', 'BAR-AB 123'];
        yield 'e-plate' => ['B-XX 123E', 'B-XX 123E'];
        yield 'h-plate' => ['B-XX 123H', 'B-XX 123H'];
        yield 'anonymized fallback' => ['XXX-XX 9999', 'XXX-XX 9999'];
        yield 'empty fallback' => ['---', '---'];
        yield 'international poland' => ['PO 12345', 'PO 12345']; // Wird nicht auto-formatiert!
        yield 'international austria' => ['W 1234 AB', 'W 1234 AB'];
        yield 'vanity plate no numbers' => ['KING', 'KING']; // Unterstützt ab sofort Custom Vanity Plates
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
        yield 'special chars ignored' => ['B-ML 1234', 'BML1234'];
        yield 'umlaute supported' => ['B-ÖÄ 99', 'BÖÄ99'];
        yield 'anonymized fallback' => ['XXX-XX 9999', 'XXXXX9999'];
    }

    #[Test]
    public function itCorrectlyComparesTwoLicensePlatesRegardlessOfFormatting(): void
    {
        $plate1 = new LicensePlate('B-ML 1234');
        $plate2 = new LicensePlate('B ML 1234');
        $plate3 = new LicensePlate('BML1234');

        $this->assertTrue($plate1->equals($plate2));
        $this->assertTrue($plate1->equals($plate3));
    }

    #[Test]
    public function itThrowsExceptionForEmptyLicensePlates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Das Kennzeichen darf nicht leer sein.');

        new LicensePlate('   ');
    }

    #[Test]
    #[DataProvider('invalidPlateProvider')]
    public function itThrowsExceptionForImplausibleLicensePlates(string $input, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new LicensePlate($input);
    }

    public static function invalidPlateProvider(): Iterator
    {
        yield 'special chars' => ['B/ML 123', 'Bitte geben Sie nur ein gültiges Kennzeichen ein'];
        yield 'too short' => ['A', 'Das Kennzeichen ist ungültig. Es muss zwischen 2 und 15 Zeichen lang sein.'];
        yield 'too long' => ['ABCDEFGHIJKLMNOPQ', 'Das Kennzeichen ist ungültig. Es muss zwischen 2 und 15 Zeichen lang sein.'];
        yield 'blocked xxx' => ['XXX', 'Bitte geben Sie ein echtes Kfz-Kennzeichen ein.'];
        yield 'blocked test' => ['TEST', 'Bitte geben Sie ein echtes Kfz-Kennzeichen ein.'];
        yield 'blocked keins' => ['KEINS', 'Bitte geben Sie ein echtes Kfz-Kennzeichen ein.'];
    }
}
