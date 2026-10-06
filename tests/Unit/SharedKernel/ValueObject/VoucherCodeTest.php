<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\VoucherCode;

covers(VoucherCode::class);

test('it accepts and normalizes voucher codes', function (string $input, string $expected): void {
    $code = new VoucherCode($input);

    expect($code->value)->toBe($expected)
        ->and((string) $code)->toBe($expected);
})->with([
    'standard code' => ['V-1234-ABCD', 'V-1234-ABCD'],
    'lowercase' => ['sommer26', 'SOMMER26'],
    'with spaces' => ['  WINTER-2026  ', 'WINTER-2026'],
]);

test('it throws exception for empty voucher codes', function (): void {
    new VoucherCode('   ');
})->throws(\InvalidArgumentException::class, 'Der Gutscheincode darf nicht leer sein.');
