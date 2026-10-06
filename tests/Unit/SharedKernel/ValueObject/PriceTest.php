<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\Price;

covers(Price::class);

test('it creates a valid price and formats it correctly', function (float $amount, string $expectedFormat): void {
    $price = new Price($amount);

    expect($price->amount)->toBe($amount)
        ->and($price->getFormatted())->toBe($expectedFormat);
})->with([
    'standard price' => [15.50, '15,50 €'],
    'zero price' => [0.0, '0,00 €'],
    'large number' => [1234.56, '1.234,56 €'],
]);

test('it identifies free prices correctly with float tolerance', function (float $amount, bool $isFree): void {
    $price = new Price($amount);

    expect($price->isFree())->toBe($isFree);
})->with([
    'exactly zero' => [0.0, true],
    'micro amount' => [0.001, true],
    'small price' => [0.01, false],
    'normal price' => [5.0, false],
]);

test('it correctly compares two prices for equality', function (): void {
    $price1 = new Price(10.50);
    $price2 = new Price(10.50);
    $price3 = new Price(10.51);

    expect($price1->equals($price2))->toBeTrue()
        ->and($price1->equals($price3))->toBeFalse();
});

test('it throws exception for negative prices', function (): void {
    new Price(-1.50);
})->throws(\InvalidArgumentException::class, 'Ein Preis darf nicht negativ sein.');
