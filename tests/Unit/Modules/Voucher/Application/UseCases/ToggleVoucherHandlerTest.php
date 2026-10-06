<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Application\UseCases;

use App\Modules\Voucher\Application\UseCases\ToggleVoucher\ToggleVoucherCommand;
use App\Modules\Voucher\Application\UseCases\ToggleVoucher\ToggleVoucherHandler;
use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToggleVoucherHandler::class)]
final class ToggleVoucherHandlerTest extends TestCase
{
    #[Test]
    #[DataProvider('toggleStatusProvider')]
    public function itActivatesAndDeactivatesAnExistingVoucher(string $targetStatus, bool $expectedActive): void
    {
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
            new DateTimeImmutable(),
        );

        $repository = $this->createMock(VoucherRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByCode')
            ->with('V-123')
            ->willReturn($voucher);

        $repository->expects($this->once())
            ->method('save')
            ->with($voucher);

        $handler = new ToggleVoucherHandler($repository);
        $handler->handle(new ToggleVoucherCommand('V-123', $targetStatus));

        $this->assertSame($expectedActive, $voucher->isActive());
        $this->assertSame(!$expectedActive, $voucher->isDeactivated());
    }

    public static function toggleStatusProvider(): array
    {
        return [
            'deactivate voucher' => ['deaktiviert', false],
            'activate voucher' => ['aktiv', true],
        ];
    }

    #[Test]
    public function itCanActivateADeactivatedVoucher(): void
    {
        // KILLT MUTANTE 5: Ein bereits inaktiver Gutschein muss korrekt aktiviert werden!
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
            new DateTimeImmutable(),
        );
        $voucher->deactivate();

        $repository = $this->createMock(VoucherRepositoryInterface::class);
        $repository->expects($this->once())->method('findByCode')->with('V-123')->willReturn($voucher);
        $repository->expects($this->once())->method('save')->with($voucher);

        $handler = new ToggleVoucherHandler($repository);
        $handler->handle(new ToggleVoucherCommand('V-123', 'aktiv'));

        $this->assertTrue($voucher->isActive());
    }

    #[Test]
    public function itThrowsAnExceptionWhenTogglingANonExistentVoucher(): void
    {
        $repository = $this->createStub(VoucherRepositoryInterface::class);
        $repository->method('findByCode')->willReturn(null);

        $handler = new ToggleVoucherHandler($repository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Gutscheincode 'V-UNKNOWN' nicht gefunden.");

        $handler->handle(new ToggleVoucherCommand('V-UNKNOWN', 'aktiv'));
    }
}
