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
    public function it_creates_a_valid_price_and_formats_it_correctly(float $amount, string $expectedFormat): void
    {
        $price = new Price($amount);

        self::assertSame($amount, $price->amount);
        self::assertSame($expectedFormat, $price->getFormatted());
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
    public function it_identifies_free_prices_correctly_with_float_tolerance(float $amount, bool $isFree): void
    {
        $price = new Price($amount);
        self::assertSame($isFree, $price->isFree());
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
    public function it_correctly_compares_two_prices_for_equality(): void
    {
        $price1 = new Price(10.50);
        $price2 = new Price(10.50);
        $price3 = new Price(10.51);

        self::assertTrue($price1->equals($price2));
        self::assertFalse($price1->equals($price3));
    }

    #[Test]
    public function it_throws_exception_for_negative_prices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ein Preis darf nicht negativ sein.');

        new Price(-1.50);
    }
}
