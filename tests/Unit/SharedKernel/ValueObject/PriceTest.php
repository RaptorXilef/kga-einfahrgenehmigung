<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\Price;
use InvalidArgumentException;
use Iterator;
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

    public static function validPriceProvider(): Iterator
    {
        yield 'standard price' => [15.50, '15,50 €'];
        yield 'zero price' => [0.0, '0,00 €'];
        yield 'large number' => [1234.56, '1.234,56 €'];
    }

    #[Test]
    #[DataProvider('freePriceProvider')]
    public function itIdentifiesFreePricesCorrectlyWithFloatTolerance(float $amount, bool $isFree): void
    {
        $price = new Price($amount);
        $this->assertSame($isFree, $price->isFree());
    }

    public static function freePriceProvider(): Iterator
    {
        yield 'exactly zero' => [0.0, true];
        yield 'micro amount' => [0.001, true];
        yield 'small price' => [0.01, false];
        yield 'normal price' => [5.0, false];
    }

    #[Test]
    public function itCorrectlyComparesTwoPricesForEquality(): void
    {
        $price1 = new Price(10.50);
        $price2 = new Price(10.50);
        $price3 = new Price(10.51);

        // FLOAT FIX: 0.0 und 0.001 ergeben exakt 0.001 ohne Float-Ungenauigkeiten (10.501 - 10.50 = 0.00099999999999945)
        $priceZero = new Price(0.0);
        $priceBoundary = new Price(0.001);

        $this->assertTrue($price1->equals($price2));
        $this->assertFalse($price1->equals($price3));

        // KILLT MUTANTE 11: Eine exakte Abweichung von 0.001 muss False ergeben!
        $this->assertFalse($priceZero->equals($priceBoundary));
    }

    #[Test]
    public function itThrowsExceptionForNegativePrices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ein Preis darf nicht negativ sein.');

        new Price(-1.50);
    }
}
