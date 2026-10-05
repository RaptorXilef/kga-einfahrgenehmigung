<?php

declare(strict_types=1);

use App\Modules\Voucher\Domain\Voucher;

\covers(Voucher::class);

\test('it creates a valid voucher with correct initial state', function (): void {
    $now = new \DateTimeImmutable('2026-10-04 12:00:00');
    $voucher = Voucher::create(
        'TEST-CODE',
        'std_7',
        'Rabattaktion',
        'percent',
        50.0,
        false,
        1,
        null,
        ['parzelle' => '123'],
        'usr_admin',
        $now,
    );

    \expect($voucher->code)->toBe('TEST-CODE')
        ->and($voucher->getCurrentUses())->toBe(0)
        ->and($voucher->isActive())->toBeTrue()
        ->and($voucher->isDeactivated())->toBeFalse()
        ->and($voucher->isExpired($now))->toBeFalse();
});

\test('it handles status toggles', function (): void {
    $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, null, [], 'U', new \DateTimeImmutable());

    $voucher->deactivate();
    \expect($voucher->isDeactivated())->toBeTrue();

    $voucher->activate();
    \expect($voucher->isActive())->toBeTrue();
});

\test('it evaluates expiration correctly', function (): void {
    $expires = new \DateTimeImmutable('2026-10-05 12:00:00');
    $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, $expires, [], 'U', new \DateTimeImmutable());

    $beforeExp = new \DateTimeImmutable('2026-10-05 11:59:59');
    $afterExp = new \DateTimeImmutable('2026-10-05 12:00:01');

    \expect($voucher->isExpired($beforeExp))->toBeFalse()
        ->and($voucher->isExpired($afterExp))->toBeTrue();
});

\test('it enforces usage limits for single-use vouchers', function (): void {
    $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, null, [], 'U', new \DateTimeImmutable());

    // First use is fine
    $voucher->recordUsage();
    \expect($voucher->getCurrentUses())->toBe(1);

    // Second use throws exception
    $voucher->recordUsage();
})->throws(\DomainException::class, 'Dieser Einmal-Gutschein wurde bereits verwendet.');

\test('it enforces usage limits for multi-use vouchers', function (): void {
    $voucher = Voucher::create('C', 'T', 'R', 'F', 0, true, 2, null, [], 'U', new \DateTimeImmutable());

    $voucher->recordUsage(); // Use 1
    $voucher->recordUsage(); // Use 2
    \expect($voucher->getCurrentUses())->toBe(2);

    // Third use throws exception
    $voucher->recordUsage();
})->throws(\DomainException::class, 'Das maximale Nutzungslimit für diesen Gutschein ist erreicht.');
