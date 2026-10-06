<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\PlotNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlotNumber::class)]
final class PlotNumberTest extends TestCase
{
    #[Test]
    #[DataProvider('validPlotProvider')]
    public function itAcceptsValidPlotNumbers(int|string $input, int $expectedValue, string $expectedFormat): void
    {
        $plot = new PlotNumber($input);
        $this->assertSame($expectedValue, $plot->value);
        $this->assertSame($expectedFormat, $plot->getFormatted());
        $this->assertSame($expectedFormat, (string) $plot);
    }

    public static function validPlotProvider(): array
    {
        return [
            'standard integer' => [42, 42, '0042'],
            'boundary zero' => [0, 0, '0000'],
            'boundary max' => [9999, 9999, '9999'],
            'string with zeros' => ['007', 7, '0007'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPlotProvider')]
    public function itThrowsExceptionForInvalidValues(int|string $invalidInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlotNumber($invalidInput);
    }

    public static function invalidPlotProvider(): array
    {
        return [
            'empty string' => ['   '],
            'negative number' => [-1],
            'over max limit' => [10000],
            'contains letters' => ['12A'],
        ];
    }
}
