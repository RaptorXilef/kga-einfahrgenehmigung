<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Application\UseCases;

use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherCommand;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherHandler;
use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherArchiveRepositoryInterface;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedeemVoucherHandler::class)]
final class RedeemVoucherHandlerTest extends TestCase
{
    #[Test]
    public function itIncrementsUsageCountAndArchivesRedemption(): void
    {
        $voucher = Voucher::create('V-123', 'std_7', 'Test', 'free', 0.0, false, 1, null, [], 'u', new DateTimeImmutable());

        $repo = $this->createMock(VoucherRepositoryInterface::class);
        $repo->expects($this->once())->method('findByCode')->with('V-123')->willReturn($voucher);
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (Voucher $v): void {
                $this->assertSame(1, $v->getCurrentUses());
            },
        );

        $archive = $this->createMock(VoucherArchiveRepositoryInterface::class);
        $archive->expects($this->once())->method('archiveRedemption')->with('V-123', 'Max', '42');

        $handler = new RedeemVoucherHandler($repo, $archive);
        $handler->handle(new RedeemVoucherCommand('V-123', 'Max', '42'));
    }

    #[Test]
    public function itThrowsExceptionIfVoucherDoesNotExist(): void
    {
        $repo = $this->createStub(VoucherRepositoryInterface::class);
        $repo->method('findByCode')->willReturn(null);

        $handler = new RedeemVoucherHandler($repo, $this->createStub(VoucherArchiveRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Gutscheincode nicht gefunden.');

        $handler->handle(new RedeemVoucherCommand('UNKNOWN', 'Max', '42'));
    }
}
