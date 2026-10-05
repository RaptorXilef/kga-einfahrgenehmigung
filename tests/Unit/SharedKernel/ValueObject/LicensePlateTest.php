<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\LicensePlate;

\covers(LicensePlate::class);

\test('it accepts and trims valid license plates', function (string $input, string $expected): void {
    $plate = new LicensePlate($input);

    \expect($plate->value)->toBe($expected);
})->with([
    'standard plate' => ['B-ML 1234', 'B-ML 1234'],
    'lowercase' => ['b-xx 99', 'B-XX 99'],
    'with spaces' => ['  HD-AB 123  ', 'HD-AB 123'],
]);

\test('it normalizes license plates for safe comparisons', function (string $input, string $normalized): void {
    $plate = new LicensePlate($input);

    \expect($plate->getNormalized())->toBe($normalized);
})->with([
    'standard plate' => ['B-ML 1234', 'BML1234'],
    'multiple spaces' => ['B  ML  1234', 'BML1234'],
    'special chars' => ['B:ML_1234!', 'BML1234'],
    'anonymized fallback' => ['XXX-XX 9999', 'XXXXX9999'],
]);

\test('it correctly compares two license plates regardless of formatting', function (): void {
    $plate1 = new LicensePlate('B-ML 1234');
    $plate2 = new LicensePlate('B ML 1234');
    $plate3 = new LicensePlate('B-ML-1234');
    $plate4 = new LicensePlate('B-ML 9999');

    \expect($plate1->equals($plate2))->toBeTrue()
        ->and($plate1->equals($plate3))->toBeTrue()
        ->and($plate1->equals($plate4))->toBeFalse();
});

\test('it throws exception for empty license plates', function (): void {
    new LicensePlate('   ');
})->throws(\InvalidArgumentException::class, 'Das Kennzeichen darf nicht leer sein.');
