<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\Price;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Price::class)]
final class PriceTest extends TestCase
{
    #[Test]
    #[DataProvider('validPriceProvider')]
    public function itCreatesAValidPriceAndFormatsItCorrectly(float $amount, string $expectedFormat): void
    {
        $price = new Price($amount);

        $this->assertSame($amount, $price->amount);
        $this->assertSame($expectedFormat, $price->getFormatted());
    }

    public static function validPriceProvider(): array
    {
        return [
            'standard price' => [15.50, '15,50 €'],
            'zero price' => [0.0, '0,00 €'],
            'large number' => [1234.56, '1.234,56 €'],
        ];
    }

    #[Test]
    #[DataProvider('freePriceProvider')]
    public function itIdentifiesFreePricesCorrectlyWithFloatTolerance(float $amount, bool $isFree): void
    {
        $price = new Price($amount);
        $this->assertSame($isFree, $price->isFree());
    }

    public static function freePriceProvider(): array
    {
        return [
            'exactly zero' => [0.0, true],
            'micro amount' => [0.001, true],
            'small price' => [0.01, false],
            'normal price' => [5.0, false],
        ];
    }

    #[Test]
    public function itCorrectlyComparesTwoPricesForEquality(): void
    {
        $price1 = new Price(10.50);
        $price2 = new Price(10.50);
        $price3 = new Price(10.51);
        // KILLT MUTANTE 11: Eine exakte Abweichung von 0.001 muss False ergeben!
        $price4 = new Price(10.501);

        $this->assertTrue($price1->equals($price2));
        $this->assertFalse($price1->equals($price3));
        $this->assertFalse($price1->equals($price4));
    }

    #[Test]
    public function itThrowsExceptionForNegativePrices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ein Preis darf nicht negativ sein.');

        new Price(-1.50);
    }
}
