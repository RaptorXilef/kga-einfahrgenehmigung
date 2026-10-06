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
            // KILLT MUTANTE 9: Wenn trim() entfernt wird, wehrt die Methode "string with spaces" ab.
            'string with spaces' => ['  123  ', 123, '0123'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPlotProvider')]
    public function itThrowsExceptionForInvalidValues(int|string $invalidInput, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        // KILLT MUTANTE 10: Wenn throw gelöscht wird, greift die ctype_digit Validierung anstelle des 'empty' Fehlers
        $this->expectExceptionMessage($expectedMessage);
        new PlotNumber($invalidInput);
    }

    public static function invalidPlotProvider(): array
    {
        return [
            'empty string' => ['   ', 'Die Parzellennummer darf nicht leer sein.'],
            'negative number' => [-1, 'Die Parzellennummer muss zwischen 1 und 9999 liegen.'],
            'over max limit' => [10000, 'Die Parzellennummer muss zwischen 1 und 9999 liegen.'],
            'contains letters' => ['12A', 'Fehler: Die Parzellennummer darf ausschließlich aus Zahlen bestehen.'],
        ];
    }
}
