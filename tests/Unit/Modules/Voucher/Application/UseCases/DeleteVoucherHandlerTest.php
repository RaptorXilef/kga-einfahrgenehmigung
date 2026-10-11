<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Application\UseCases;

use App\Modules\Voucher\Application\UseCases\DeleteVoucher\DeleteVoucherCommand;
use App\Modules\Voucher\Application\UseCases\DeleteVoucher\DeleteVoucherHandler;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteVoucherHandler::class)]
final class DeleteVoucherHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesDeletionToRepository(): void
    {
        $repo = $this->createMock(VoucherRepositoryInterface::class);
        $repo->expects($this->once())->method('delete')->with('V-1234');

        $handler = new DeleteVoucherHandler($repo);
        $handler->handle(new DeleteVoucherCommand('V-1234'));
    }
}
