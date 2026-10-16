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

        // International Eurasian Plates (Must NOT be mangled by auto-formatter)
        yield 'international poland' => ['PO 12345', 'PO 12345'];
        yield 'international romania' => ['B 123 ABC', 'B 123 ABC'];
        yield 'international austria' => ['W 1234 AB', 'W 1234 AB'];
        yield 'international russia cyrillic' => ['А 123 АА 77', 'А 123 АА 77'];
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
        yield 'cyrillic supported' => ['А 123 АА 77', 'А123АА77'];
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
        yield 'too short' => ['A1', 'Das Kennzeichen ist ungültig. Es muss zwischen 2 und 15 Zeichen lang sein.'];
        yield 'too long' => ['ABCDEFGHIJKLMNOPQ', 'Das Kennzeichen ist ungültig. Es muss zwischen 2 und 15 Zeichen lang sein.'];

        // Strict Eurasian Plausibility (Requires Letters AND Numbers)
        yield 'only letters (XXXXX)' => ['XXXXX', 'Bitte geben Sie ein plausibles Kfz-Kennzeichen ein'];
        yield 'only letters (TEST)' => ['TEST', 'Bitte geben Sie ein plausibles Kfz-Kennzeichen ein'];
        yield 'only digits' => ['12345', 'Bitte geben Sie ein plausibles Kfz-Kennzeichen ein'];

        // Sub-Blacklist bypass attempts
        yield 'blocked test with number' => ['TEST 1', 'Bitte geben Sie ein echtes Kfz-Kennzeichen ein.'];
        yield 'blocked xxx with number' => ['XXX 99', 'Bitte geben Sie ein echtes Kfz-Kennzeichen ein.'];
        yield 'blocked pkw with number' => ['PKW 123', 'Bitte geben Sie ein echtes Kfz-Kennzeichen ein.'];
    }
}
