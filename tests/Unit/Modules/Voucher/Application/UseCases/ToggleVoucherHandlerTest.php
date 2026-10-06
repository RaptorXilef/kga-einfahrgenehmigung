<?php

declare(strict_types=1);

use App\Modules\Voucher\Application\UseCases\ToggleVoucher\ToggleVoucherCommand;
use App\Modules\Voucher\Application\UseCases\ToggleVoucher\ToggleVoucherHandler;
use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;

covers(ToggleVoucherHandler::class);

test('it activates and deactivates an existing voucher', function (string $targetStatus, bool $expectedActive): void {
    // Gutschein erstellen (Start-Status ist immer 'aktiv' durch die Factory)
    $voucher = Voucher::create(
        'V-123',
        'std_7',
        'Test',
        'free',
        0.0,
        false,
        1,
        null,
        [],
        'admin',
        new \DateTimeImmutable(),
    );

    /** @var VoucherRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
    $repository = $this->createMock(VoucherRepositoryInterface::class);

    $repository->expects($this->once())
        ->method('findByCode')
        ->with('V-123')
        ->willReturn($voucher);

    // Save MUSS aufgerufen werden
    $repository->expects($this->once())
        ->method('save')
        ->with($voucher);

    $handler = new ToggleVoucherHandler($repository);
    $handler->handle(new ToggleVoucherCommand('V-123', $targetStatus));

    expect($voucher->isActive())->toBe($expectedActive)
        ->and($voucher->isDeactivated())->toBe(!$expectedActive);
})->with([
    'deactivate voucher' => ['deaktiviert', false],
    'activate voucher' => ['aktiv', true],
]);

test('it throws an exception when toggling a non-existent voucher', function (): void {
    /** @var VoucherRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repository */
    $repository = $this->createStub(VoucherRepositoryInterface::class);
    $repository->method('findByCode')->willReturn(null);

    $handler = new ToggleVoucherHandler($repository);

    $handler->handle(new ToggleVoucherCommand('V-UNKNOWN', 'aktiv'));
})->throws(\DomainException::class, "Gutscheincode 'V-UNKNOWN' nicht gefunden.");
