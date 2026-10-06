<?php

declare(strict_types=1);

use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;

covers(Permit::class);

function createTestPermit(\DateTimeImmutable $start, \DateTimeImmutable $end, PermitStatus $status = PermitStatus::Offen): Permit
{
    return new Permit(
        code: new PermitCode('TEST-1234'),
        template_key: new TemplateKey('std_7'),
        owner: new Owner('Max Mustermann', new EmailAddress('test@example.com'), new PlotNumber(42)),
        vehicle: new Vehicle('pkw', new LicensePlate('B-XX 123'), 'Test Firma'),
        validity: new Validity($start, $end, new Price(10.0), 'Bau'),
        status: new Status($status),
        erstellt: new \DateTimeImmutable('2026-10-01 10:00:00'),
    );
}

test('it initializes correctly and returns data via getters', function (): void {
    $start = new \DateTimeImmutable('2026-10-10');
    $end = new \DateTimeImmutable('2026-10-17');
    $permit = \createTestPermit($start, $end);

    expect($permit->code->value)->toBe('TEST-1234')
        ->and($permit->getOwnerName())->toBe('Max Mustermann')
        ->and($permit->getPlotNumber())->toBe('0042')
        ->and($permit->getOwnerEmail())->toBe('test@example.com')
        ->and($permit->getLicensePlate())->toBe('B-XX 123')
        ->and($permit->getCompany())->toBe('Test Firma')
        ->and($permit->getPrice())->toBe(10.0)
        ->and($permit->isPaid())->toBeFalse()
        ->and($permit->isSuspended())->toBeFalse();
});

test('it handles payment status transitions', function (): void {
    $permit = \createTestPermit(new \DateTimeImmutable(), new \DateTimeImmutable());

    $paymentDate = new \DateTimeImmutable('2026-10-05 14:00:00');
    $permit->markAsPaid('PayPal Transaktion', $paymentDate);

    expect($permit->isPaid())->toBeTrue()
        ->and($permit->getStatus())->toBe(PermitStatus::Bezahlt)
        ->and($permit->getInternalComment())->toBe('PayPal Transaktion')
        ->and($permit->getPaidAt())->toEqual($paymentDate);
});

test('it handles suspension logic', function (): void {
    $permit = \createTestPermit(new \DateTimeImmutable(), new \DateTimeImmutable());

    $permit->suspend('Gartenordnung verletzt');
    expect($permit->isSuspended())->toBeTrue()
        ->and($permit->getSuspensionReason())->toBe('Gartenordnung verletzt');

    $permit->unsuspend();
    expect($permit->isSuspended())->toBeFalse()
        ->and($permit->getSuspensionReason())->toBeNull();
});

test('it records payment reminders', function (): void {
    $permit = \createTestPermit(new \DateTimeImmutable(), new \DateTimeImmutable());

    $now = new \DateTimeImmutable('2026-10-05 10:00:00');
    $permit->recordReminder($now);

    expect($permit->getStatusObject()->last_reminder_at)->toEqual($now);
});

test('it validates time validity correctly', function (): void {
    $start = new \DateTimeImmutable('2026-10-10 00:00:00');
    $end = new \DateTimeImmutable('2026-10-17 00:00:00');

    $permit = \createTestPermit($start, $end);

    // Boundary Testing
    $before = new \DateTimeImmutable('2026-10-09 23:59:59');
    $inside = new \DateTimeImmutable('2026-10-12 12:00:00');
    $after = new \DateTimeImmutable('2026-10-18 00:00:01');
    $exactEnd = new \DateTimeImmutable('2026-10-17 23:59:59');

    // Without payment requirement
    expect($permit->isValid(false, $before))->toBeFalse()
        ->and($permit->isValid(false, $inside))->toBeTrue()
        ->and($permit->isValid(false, $exactEnd))->toBeTrue()
        ->and($permit->isValid(false, $after))->toBeFalse()
        ->and($permit->isExpired($after))->toBeTrue()
        ->and($permit->isFuture($before))->toBeTrue();

    // With payment requirement (permit is currently 'Offen')
    expect($permit->isValid(true, $inside))->toBeFalse();

    // Pay it, then it should be valid
    $permit->markAsPaid();
    expect($permit->isValid(true, $inside))->toBeTrue();
});
