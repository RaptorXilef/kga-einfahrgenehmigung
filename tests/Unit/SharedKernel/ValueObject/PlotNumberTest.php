<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\PlotNumber;
use InvalidArgumentException;

\covers(PlotNumber::class);

\test('it accepts valid plot numbers and formats them correctly', function (int|string $input, int $expectedValue, string $expectedFormat): void {
    $plot = new PlotNumber($input);

    \expect($plot->value)->toBe($expectedValue)
        ->and($plot->getFormatted())->toBe($expectedFormat)
        ->and((string) $plot)->toBe($expectedFormat);
})->with([
    'standard integer' => [42, 42, '0042'],
    'boundary zero' => [0, 0, '0000'],
    'boundary max' => [9999, 9999, '9999'],
    'string with zeros' => ['007', 7, '0007'],
    'string with spaces' => ['  123  ', 123, '0123'],
]);

\test('it throws exception for invalid values', function (int|string $invalidInput): void {
    new PlotNumber($invalidInput);
})->with([
    'empty string' => '   ',
    'negative number' => -1,
    'over max limit' => 10000,
    'contains letters' => '12A',
    'special characters' => '12-3',
])->throws(InvalidArgumentException::class);

\test('it correctly compares two plot numbers for equality', function (): void {
    $plot1 = new PlotNumber(123);
    $plot2 = new PlotNumber('0123');
    $plot3 = new PlotNumber(124);

    \expect($plot1->equals($plot2))->toBeTrue()
        ->and($plot1->equals($plot3))->toBeFalse();
});
